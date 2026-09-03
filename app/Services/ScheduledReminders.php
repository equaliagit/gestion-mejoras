<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\Scopes\VisibilityScope;
use App\Models\User;
use App\Notifications\DeadlineReminder;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Los avisos que no dispara nadie pulsando un botón, sino el paso del tiempo.
 *
 * Todo el trabajo ocurre DENTRO de este proceso, sin lanzar subprocesos: el
 * alojamiento tiene `proc_open` deshabilitado (nos lo habilitan solo mientras
 * dura el despliegue), y el programador de Laravel, usado de la forma
 * habitual, lanza cada tarea en un proceso aparte. Escrito así funciona igual
 * y sigue funcionando el día que Hostytec restaure su configuración.
 */
class ScheduledReminders
{
    /**
     * Ejecuta los tres avisos del día y devuelve cuántos ha mandado de cada uno.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        return [
            'plazos_vencidos' => $this->avisarDePlazosVencidos(),
            'aplazadas_a_revisar' => $this->avisarDeAplazadasQueTocan(),
            'borradores_olvidados' => $this->avisarDeBorradoresOlvidados(),
        ];
    }

    /**
     * Propuestas aprobadas cuya fecha de fin prevista ya pasó y que siguen
     * sin cerrarse. Se avisa al responsable y al comité.
     */
    private function avisarDePlazosVencidos(): int
    {
        $propuestas = Proposal::withoutGlobalScope(VisibilityScope::class)
            ->submitted()
            ->whereNull('closed_on')
            ->whereNotNull('planned_end_on')
            ->whereDate('planned_end_on', '<', today())
            ->whereHas('status', fn ($q) => $q->where('code', Status::APPROVED))
            ->with(['implementer', 'status'])
            ->get();

        foreach ($propuestas as $propuesta) {
            $destinatarios = $this->comite();

            if ($propuesta->implementer) {
                $destinatarios = $destinatarios->push($propuesta->implementer)->unique('id');
            }

            Notification::send($destinatarios, new DeadlineReminder(
                $propuesta,
                DeadlineReminder::PLAZO_VENCIDO,
            ));
        }

        return $propuestas->count();
    }

    /**
     * Aplazadas que llegan a su fecha de revisión. Se avisa al comité y se
     * limpia la fecha, para no repetir el aviso todos los días.
     */
    private function avisarDeAplazadasQueTocan(): int
    {
        $propuestas = Proposal::withoutGlobalScope(VisibilityScope::class)
            ->submitted()
            ->whereNotNull('revisit_on')
            ->whereDate('revisit_on', '<=', today())
            ->whereHas('status', fn ($q) => $q->where('code', Status::POSTPONED))
            ->get();

        foreach ($propuestas as $propuesta) {
            Notification::send($this->comite(), new DeadlineReminder(
                $propuesta,
                DeadlineReminder::APLAZADA_A_REVISAR,
            ));

            $propuesta->forceFill(['revisit_on' => null])->save();
        }

        return $propuestas->count();
    }

    /**
     * Borradores parados 60 días: recordatorio a su autor. A los 90 se borran
     * en silencio, para que la lista no se convierta en un cementerio.
     */
    private function avisarDeBorradoresOlvidados(): int
    {
        $avisados = 0;

        Proposal::withoutGlobalScope(VisibilityScope::class)
            ->drafts()
            ->whereDate('updated_at', '<=', today()->subDays(60))
            ->whereDate('updated_at', '>', today()->subDays(90))
            ->with('author')
            ->get()
            ->each(function (Proposal $propuesta) use (&$avisados) {
                if ($propuesta->author) {
                    $propuesta->author->notify(new DeadlineReminder(
                        $propuesta,
                        DeadlineReminder::BORRADOR_OLVIDADO,
                    ));
                    $avisados++;
                }
            });

        Proposal::withoutGlobalScope(VisibilityScope::class)
            ->drafts()
            ->whereDate('updated_at', '<=', today()->subDays(90))
            ->get()
            ->each(fn (Proposal $p) => $p->delete());

        return $avisados;
    }

    /** @return Collection<int, User> */
    private function comite(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission(Permissions::REVIEW)
            ->get();
    }
}
