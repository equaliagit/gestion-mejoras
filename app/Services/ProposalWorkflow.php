<?php

namespace App\Services;

use App\Enums\Visibility;
use App\Events\ProposalStatusChanged;
use App\Exceptions\InvalidTransition;
use App\Models\Comment;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\Scopes\VisibilityScope;
use App\Models\StatusChange;
use App\Models\User;
use App\Support\Workflow;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * El único sitio de la aplicación donde una propuesta cambia de estado.
 *
 * Ninguna pantalla toca `status_id` a mano. Todas pasan por aquí, y aquí
 * ocurren siempre las cuatro cosas juntas, o ninguna:
 *
 *   1. se comprueba que el flujo permite ese salto
 *   2. se comprueba que están los datos que ese salto exige
 *   3. se actualiza el estado de la propuesta
 *   4. se escribe la fila del historial y se avisa a quien corresponda
 *
 * Que vayan juntas es la razón de que exista esta clase: si alguien pudiera
 * cambiar el estado sin escribir el historial, el historial dejaría de ser
 * fiable y con él se caerían los informes y los avisos.
 */
class ProposalWorkflow
{
    /**
     * Crea el borrador. Todavía no tiene número ni lo ve nadie más.
     *
     * @param  array<string, mixed>  $data
     */
    public function startDraft(User $author, array $data): Proposal
    {
        $proposal = new Proposal;
        $proposal->fill($data);
        $proposal->user_id = $author->id;
        $proposal->status_id = Status::idFor(Status::NEW);
        $proposal->visibility = $data['visibility'] ?? Visibility::Public;
        $proposal->save();

        return $proposal;
    }

    /**
     * Enviar la propuesta: aquí es donde recibe número y deja de ser borrador.
     * El número se asigna ahora, no al crear el borrador, para no dejar huecos
     * en la numeración por borradores que nadie llegó a enviar.
     */
    public function submit(Proposal $proposal, User $actor): Proposal
    {
        if (! $proposal->isDraft()) {
            throw new InvalidTransition('Esta propuesta ya se había enviado.');
        }

        return DB::transaction(function () use ($proposal, $actor) {
            $proposal->submitted_at = now();
            $proposal->reference = $this->nextReference(now());
            $proposal->save();

            $this->record($proposal, from: null, to: Status::NEW, actor: $actor);

            return $proposal->refresh();
        });
    }

    /** Un miembro del comité se hace cargo de la propuesta. */
    public function assignReviewer(Proposal $proposal, User $reviewer, User $actor, ?string $comment = null): Proposal
    {
        return DB::transaction(function () use ($proposal, $reviewer, $actor, $comment) {
            $proposal->reviewer_id = $reviewer->id;
            $proposal->save();

            return $this->moveTo($proposal, Status::IN_REVIEW, $actor, $comment);
        });
    }

    /**
     * Se le pide una aclaración al proponente; la propuesta queda esperando.
     *
     * La pregunta se publica en el hilo visible, no solo en el historial: si
     * quien tiene que contestar no puede leerla, la propuesta se queda parada
     * para siempre y nadie entiende por qué.
     */
    public function requestInfo(Proposal $proposal, User $actor, string $question): Proposal
    {
        if (trim($question) === '') {
            throw InvalidTransition::missing('escribir la pregunta al proponente', Status::AWAITING_INFO);
        }

        return DB::transaction(function () use ($proposal, $actor, $question) {
            Comment::create([
                'proposal_id' => $proposal->id,
                'user_id' => $actor->id,
                'body' => $question,
                'is_internal' => false,
            ]);

            return $this->moveTo($proposal, Status::AWAITING_INFO, $actor, $question);
        });
    }

    /** El proponente contesta y vuelve sola a revisión. */
    public function infoProvided(Proposal $proposal, User $actor, ?string $comment = null): Proposal
    {
        return $this->moveTo($proposal, Status::IN_REVIEW, $actor, $comment);
    }

    /** Entra en el orden del día de una sesión concreta. */
    public function sendToCommittee(Proposal $proposal, CommitteeSession $session, User $actor): Proposal
    {
        return DB::transaction(function () use ($proposal, $session, $actor) {
            $proposal->committee_session_id = $session->id;
            $proposal->save();

            return $this->moveTo(
                $proposal,
                Status::IN_COMMITTEE,
                $actor,
                'Orden del día del '.$session->held_on->format('d/m/Y'),
            );
        });
    }

    public function approve(Proposal $proposal, User $actor, ?string $comment = null): Proposal
    {
        return DB::transaction(function () use ($proposal, $actor, $comment) {
            $proposal->decided_at = now();
            $proposal->decision_reason = $comment;
            $proposal->save();

            return $this->moveTo($proposal, Status::APPROVED, $actor, $comment);
        });
    }

    /** Rechazar exige motivo: es lo que se le comunica al proponente. */
    public function reject(Proposal $proposal, User $actor, string $reason): Proposal
    {
        return DB::transaction(function () use ($proposal, $actor, $reason) {
            $proposal->decided_at = now();
            $proposal->decision_reason = $reason;
            $proposal->save();

            return $this->moveTo($proposal, Status::REJECTED, $actor, $reason);
        });
    }

    /** Aplazar exige motivo y, además, la fecha en que se vuelve a mirar. */
    public function postpone(Proposal $proposal, User $actor, string $reason, CarbonInterface $revisitOn): Proposal
    {
        if ($revisitOn->isPast()) {
            throw InvalidTransition::missing('una fecha de revisión futura', Status::POSTPONED);
        }

        return DB::transaction(function () use ($proposal, $actor, $reason, $revisitOn) {
            $proposal->decided_at = now();
            $proposal->decision_reason = $reason;
            $proposal->revisit_on = $revisitOn;
            $proposal->save();

            return $this->moveTo($proposal, Status::POSTPONED, $actor, $reason);
        });
    }

    /**
     * Planificar la implantación. No cambia de estado —sigue en «Aprobada»—,
     * pero es el momento en que la propuesta pasa a tener plazos que vigilar.
     */
    public function planImplementation(
        Proposal $proposal,
        User $implementer,
        CarbonInterface $startOn,
        CarbonInterface $endOn,
    ): Proposal {
        if ($endOn->lessThan($startOn)) {
            throw new InvalidTransition('La fecha de fin no puede ser anterior a la de inicio.');
        }

        $proposal->implementer_id = $implementer->id;
        $proposal->planned_start_on = $startOn;
        $proposal->planned_end_on = $endOn;
        $proposal->save();

        return $proposal;
    }

    /** Marca el arranque real. Tampoco cambia de estado. */
    public function markStarted(Proposal $proposal, ?CarbonInterface $on = null): Proposal
    {
        $proposal->started_on = $on ?? now();
        $proposal->save();

        return $proposal;
    }

    /** Cerrar exige contar qué se consiguió: es lo que se comunica al final. */
    public function markImplemented(Proposal $proposal, User $actor, string $resultSummary, ?CarbonInterface $closedOn = null): Proposal
    {
        if (trim($resultSummary) === '') {
            throw InvalidTransition::missing('un resumen del resultado', Status::IMPLEMENTED);
        }

        return DB::transaction(function () use ($proposal, $actor, $resultSummary, $closedOn) {
            $proposal->result_summary = $resultSummary;
            $proposal->closed_on = $closedOn ?? now();
            $proposal->started_on ??= $proposal->planned_start_on ?? $proposal->closed_on;
            $proposal->save();

            return $this->moveTo($proposal, Status::IMPLEMENTED, $actor, $resultSummary);
        });
    }

    /** Volver a poner en revisión algo rechazado o aplazado. */
    public function reopen(Proposal $proposal, User $actor, string $comment): Proposal
    {
        if (trim($comment) === '') {
            throw InvalidTransition::missing('explicar por qué se reabre', Status::IN_REVIEW);
        }

        return DB::transaction(function () use ($proposal, $actor, $comment) {
            $proposal->revisit_on = null;
            $proposal->save();

            return $this->moveTo($proposal, Status::IN_REVIEW, $actor, $comment);
        });
    }

    // ------------------------------------------------------------------ Interior

    /**
     * El paso común a todas las transiciones. Comprueba, mueve y deja registro.
     */
    private function moveTo(Proposal $proposal, string $to, User $actor, ?string $comment = null): Proposal
    {
        // El estado de partida se lee de la base de datos, no de la relación
        // que el modelo tenga cargada: si alguien tocó la propuesta por otro
        // lado, esa copia en memoria puede estar obsoleta y validaríamos el
        // salto contra un estado que ya no es el real.
        $from = Status::query()->whereKey($proposal->status_id)->value('code');

        if (! Workflow::allows($from, $to)) {
            throw InvalidTransition::between($from, $to);
        }

        $target = Status::query()->where('code', $to)->firstOrFail();

        if ($target->requires_reason && trim((string) $comment) === '') {
            throw InvalidTransition::missingReason($to);
        }

        return DB::transaction(function () use ($proposal, $from, $target, $actor, $comment) {
            $proposal->status_id = $target->id;
            $proposal->save();

            $this->record($proposal, from: $from, to: $target->code, actor: $actor, comment: $comment);

            return $proposal->refresh();
        });
    }

    /** Escribe la fila del historial y avisa de que algo ha cambiado. */
    private function record(Proposal $proposal, ?string $from, string $to, User $actor, ?string $comment = null): StatusChange
    {
        $change = StatusChange::create([
            'proposal_id' => $proposal->id,
            'from_status_id' => $from ? Status::idFor($from) : null,
            'to_status_id' => Status::idFor($to),
            'user_id' => $actor->id,
            'comment' => $comment,
        ]);

        ProposalStatusChanged::dispatch($proposal, $change);

        return $change;
    }

    /**
     * El siguiente número del año: MEJ-26-0001, MEJ-26-0002...
     * Se calcula dentro de la transacción y con la fila bloqueada para que dos
     * personas que envíen a la vez no se lleven el mismo número.
     */
    private function nextReference(CarbonInterface $date): string
    {
        $prefix = 'MEJ-'.$date->format('y').'-';

        $last = Proposal::withoutGlobalScope(VisibilityScope::class)
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->lockForUpdate()
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
