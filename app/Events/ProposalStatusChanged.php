<?php

namespace App\Events;

use App\Models\Proposal;
use App\Models\StatusChange;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara con cada cambio de estado, sea quien sea quien lo provoque.
 *
 * Es el enganche del que colgarán los avisos por correo en la fase 4: en vez
 * de que cada pantalla se acuerde de avisar a alguien, hay un único punto por
 * el que pasan todos los cambios. Añadir un estado nuevo mañana no obliga a
 * repasar la aplicación entera buscando dónde faltaba notificar.
 */
class ProposalStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Proposal $proposal,
        public readonly StatusChange $change,
    ) {}
}
