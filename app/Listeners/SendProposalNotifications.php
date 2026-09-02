<?php

namespace App\Listeners;

use App\Enums\Visibility;
use App\Events\ProposalStatusChanged;
use App\Models\ProposalStatus as Status;
use App\Models\User;
use App\Notifications\ProposalUpdate;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Quién se entera de cada cambio de estado.
 *
 * Esta clase es la matriz de avisos del documento, hecha código. Está sola
 * escuchando a ProposalStatusChanged, así que ninguna pantalla tiene que
 * acordarse de notificar: si el estado cambió, el aviso sale.
 */
class SendProposalNotifications
{
    public function handle(ProposalStatusChanged $evento): void
    {
        $propuesta = $evento->proposal;
        $cambio = $evento->change;
        $codigo = $cambio->toStatus->code;

        // Al proponente siempre, salvo cuando el movimiento lo ha hecho él
        // mismo: nadie necesita un correo confirmándole lo que acaba de hacer.
        // La excepción es el registro, que sí lleva acuse de recibo.
        $avisarAlAutor = $cambio->user_id !== $propuesta->user_id
            || $codigo === Status::NEW;

        if ($avisarAlAutor && $propuesta->author) {
            $propuesta->author->notify(new ProposalUpdate($propuesta, $cambio, ProposalUpdate::PARA_AUTOR));
        }

        // Al comité, cuando entra algo nuevo o cuando hay que decidir.
        if (in_array($codigo, [Status::NEW, Status::IN_COMMITTEE], true)) {
            Notification::send(
                $this->comite()->reject(fn (User $u) => $u->id === $cambio->user_id),
                new ProposalUpdate($propuesta, $cambio, ProposalUpdate::PARA_COMITE),
            );
        }

        // Cuando quien propuso contesta a una petición de información, el
        // revisor tiene que enterarse: si no, la respuesta se queda ahí
        // esperando a que alguien entre a mirar por casualidad.
        if ($codigo === Status::IN_REVIEW
            && $cambio->user_id === $propuesta->user_id
            && $propuesta->reviewer
            && $propuesta->reviewer_id !== $cambio->user_id) {
            $propuesta->reviewer->notify(new ProposalUpdate($propuesta, $cambio, ProposalUpdate::PARA_REVISOR));
        }

        // Al revisor asignado, cuando la decisión la tomó otra persona.
        if (in_array($codigo, [Status::APPROVED, Status::REJECTED, Status::POSTPONED], true)
            && $propuesta->reviewer
            && $propuesta->reviewer_id !== $cambio->user_id
            && $propuesta->reviewer_id !== $propuesta->user_id) {
            $propuesta->reviewer->notify(new ProposalUpdate($propuesta, $cambio, ProposalUpdate::PARA_AUTOR));
        }

        // Y a toda la plantilla cuando se implanta algo público: es lo que
        // hace que la gente siga proponiendo. Las privadas y las anónimas no.
        if ($codigo === Status::IMPLEMENTED && $propuesta->visibility === Visibility::Public) {
            Notification::send(
                $this->plantilla()->reject(fn (User $u) => $u->id === $propuesta->user_id),
                new ProposalUpdate($propuesta, $cambio, ProposalUpdate::PARA_TODOS),
            );
        }
    }

    /** @return Collection<int, User> */
    private function comite(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission(Permissions::REVIEW)
            ->get();
    }

    /** @return Collection<int, User> */
    private function plantilla(): Collection
    {
        return User::query()->where('is_active', true)->get();
    }
}
