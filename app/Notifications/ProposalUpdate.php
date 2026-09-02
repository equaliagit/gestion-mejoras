<?php

namespace App\Notifications;

use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\StatusChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El aviso de que una propuesta se ha movido.
 *
 * Una sola clase para todos los casos: lo que cambia es el texto, y todos los
 * textos están juntos en el método `guion()`. Así, cuando alguien quiera
 * retocar cómo se comunica un rechazo, sabe exactamente dónde mirar, y añadir
 * un estado nuevo es añadir un caso más, no escribir otra clase entera.
 *
 * ShouldQueue: el aviso no se envía durante la petición. Se deja apuntado y lo
 * manda un proceso aparte, para que nadie se quede mirando una pantalla en
 * blanco porque el servidor de correo va lento.
 */
class ProposalUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public const PARA_AUTOR = 'autor';

    public const PARA_COMITE = 'comite';

    public const PARA_REVISOR = 'revisor';

    public const PARA_TODOS = 'todos';

    public function __construct(
        public readonly Proposal $proposal,
        public readonly StatusChange $change,
        public readonly string $publico = self::PARA_AUTOR,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$asunto, $entrada, $detalle] = $this->guion();

        $mensaje = (new MailMessage)
            ->subject($asunto)
            ->greeting('Hola'.($notifiable->name ? ', '.strtok($notifiable->name, ' ') : '').':')
            ->line($entrada)
            ->line('**'.$this->proposal->reference.' · '.$this->proposal->title.'**');

        if ($detalle) {
            $mensaje->line($detalle);
        }

        return $mensaje
            ->action('Ver la propuesta', route('proposals.show', $this->proposal))
            ->salutation('Buzón de Mejora');
    }

    /** Lo que se guarda para el buzón dentro de la aplicación. */
    public function toArray(object $notifiable): array
    {
        [$asunto, $entrada] = $this->guion();

        return [
            'proposal_id' => $this->proposal->id,
            'reference' => $this->proposal->reference,
            'title' => $this->proposal->title,
            'estado' => $this->change->toStatus->name,
            'titular' => $asunto,
            'detalle' => $entrada,
        ];
    }

    /**
     * Los textos, todos juntos.
     *
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function guion(): array
    {
        $ref = $this->proposal->reference;
        $motivo = trim((string) $this->change->comment);

        return match (true) {
            $this->publico === self::PARA_TODOS => [
                'Una propuesta de mejora ya está en marcha',
                'Se ha implantado una propuesta que alguien de la casa registró. Así queda:',
                $this->proposal->result_summary,
            ],

            $this->change->toStatus->hasCode(Status::NEW) && $this->publico === self::PARA_COMITE => [
                "Nueva propuesta: {$ref}",
                'Ha entrado una propuesta nueva y está sin asignar.',
                null,
            ],

            $this->change->toStatus->hasCode(Status::NEW) => [
                "Hemos recibido tu propuesta {$ref}",
                'Queda registrada. El comité la revisará y te iremos contando.',
                null,
            ],

            $this->change->toStatus->hasCode(Status::IN_REVIEW) && $this->publico === self::PARA_REVISOR => [
                "Te han contestado en {$ref}",
                'Quien la propuso ha respondido a tu pregunta, así que vuelve a estar en revisión.',
                $motivo !== '' ? '«'.$motivo.'»' : null,
            ],

            $this->change->toStatus->hasCode(Status::IN_REVIEW) => [
                "Tu propuesta {$ref} está en revisión",
                'Un miembro del comité se ha hecho cargo de ella.',
                $this->proposal->reviewer?->name ? 'La lleva '.$this->proposal->reviewer->name.'.' : null,
            ],

            $this->change->toStatus->hasCode(Status::AWAITING_INFO) => [
                "Te preguntan algo sobre {$ref}",
                'El comité necesita una aclaración para seguir. Puedes contestar en el hilo de la propuesta.',
                $motivo !== '' ? '«'.$motivo.'»' : null,
            ],

            $this->change->toStatus->hasCode(Status::IN_COMMITTEE) => [
                "{$ref} pasa al comité",
                'Ha terminado la revisión y entra en el orden del día de la próxima sesión.',
                $this->proposal->committeeSession
                    ? 'Sesión del '.$this->proposal->committeeSession->held_on->format('d/m/Y').'.'
                    : null,
            ],

            $this->change->toStatus->hasCode(Status::APPROVED) => [
                "Tu propuesta {$ref} ha sido aprobada",
                'El comité le ha dado el visto bueno. El siguiente paso es planificar la implantación.',
                $motivo !== '' ? $motivo : null,
            ],

            $this->change->toStatus->hasCode(Status::REJECTED) => [
                "Decisión sobre {$ref}",
                'El comité ha decidido no seguir adelante con esta propuesta. El motivo es este:',
                $motivo,
            ],

            $this->change->toStatus->hasCode(Status::POSTPONED) => [
                "{$ref} queda aplazada",
                'La propuesta es interesante, pero no es el momento. Se volverá a mirar más adelante.',
                trim($motivo.' '.($this->proposal->revisit_on
                    ? 'Fecha de revisión: '.$this->proposal->revisit_on->format('d/m/Y').'.'
                    : '')),
            ],

            $this->change->toStatus->hasCode(Status::IMPLEMENTED) => [
                "Tu propuesta {$ref} ya está implantada",
                'Se ha puesto en marcha. Gracias por proponerla.',
                $this->proposal->result_summary,
            ],

            default => [
                "Novedades en {$ref}",
                'La propuesta ha cambiado de estado a «'.$this->change->toStatus->name.'».',
                $motivo !== '' ? $motivo : null,
            ],
        };
    }
}
