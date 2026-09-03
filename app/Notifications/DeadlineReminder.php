<?php

namespace App\Notifications;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Los avisos que dispara el calendario, no una persona.
 *
 * Mismo planteamiento que ProposalUpdate: una clase, los textos juntos.
 */
class DeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public const PLAZO_VENCIDO = 'plazo_vencido';

    public const APLAZADA_A_REVISAR = 'aplazada_a_revisar';

    public const BORRADOR_OLVIDADO = 'borrador_olvidado';

    public function __construct(
        public readonly Proposal $proposal,
        public readonly string $motivo,
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
            ->line('**'.($this->proposal->reference ?? 'Borrador').' · '.$this->proposal->title.'**');

        if ($detalle) {
            $mensaje->line($detalle);
        }

        return $mensaje
            ->action('Ver la propuesta', route('proposals.show', $this->proposal))
            ->salutation('Buzón de Mejora');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        [$asunto, $entrada] = $this->guion();

        return [
            'proposal_id' => $this->proposal->id,
            'reference' => $this->proposal->reference,
            'title' => $this->proposal->title,
            'titular' => $asunto,
            'detalle' => $entrada,
        ];
    }

    /** @return array{0: string, 1: string, 2: ?string} */
    private function guion(): array
    {
        $ref = $this->proposal->reference ?? 'tu borrador';

        return match ($this->motivo) {
            self::PLAZO_VENCIDO => [
                "Se ha pasado el plazo de {$ref}",
                'La fecha de fin prevista para implantar esta propuesta ya pasó y sigue abierta.',
                $this->proposal->planned_end_on
                    ? 'Estaba prevista para el '.$this->proposal->planned_end_on->format('d/m/Y').
                      '. Si ya está hecha, ciérrala; si no, ajusta las fechas.'
                    : null,
            ],

            self::APLAZADA_A_REVISAR => [
                "Toca volver a mirar {$ref}",
                'Esta propuesta se aplazó y hoy es la fecha que el comité marcó para retomarla.',
                $this->proposal->decision_reason
                    ? 'Entonces se dijo: «'.$this->proposal->decision_reason.'»'
                    : null,
            ],

            self::BORRADOR_OLVIDADO => [
                'Tienes una propuesta a medio escribir',
                'Empezaste a escribir esta propuesta hace dos meses y se quedó sin enviar. Solo la ves tú.',
                'Si ya no te interesa, no hagas nada: se borrará sola dentro de un mes.',
            ],

            default => [
                "Novedades en {$ref}",
                'Hay algo pendiente con esta propuesta.',
                null,
            ],
        };
    }
}
