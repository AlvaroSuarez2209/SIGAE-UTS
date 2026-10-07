<?php

namespace App\Notifications\Leadership;

use App\Models\Leadership;
use App\Services\Reports\ReportTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Al líder, cuando Coordinación le asigna un liderazgo nuevo — antes,
 * nadie le avisaba: se enteraba solo si entraba a la aplicación y veía
 * que ya tenía algo en su panel o en su bandeja de revisión. Disparada
 * desde LeadershipForm::save(), solo al CREAR un liderazgo (nunca al
 * editar uno existente, ej. solo extender su fecha de fin).
 *
 * Reutiliza el mismo layout que las notificaciones de evidencias
 * (resources/views/mail/status-notification.blade.php, vía
 * `primaryLabel`/`primaryValue` genéricos) con "Ámbito" en vez de
 * "Entregable" — no hay una Evidence detrás, así que no extiende
 * App\Notifications\Evidence\EvidenceStatusNotification.
 */
class LeadershipAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly Leadership $leadership) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tone = ReportTheme::statusTone('Líder asignado')
            ?? ['bg' => ReportTheme::SURFACE_MUTED, 'text' => ReportTheme::SECONDARY];

        return (new MailMessage)
            ->subject('Nuevo liderazgo asignado — SIGAE-UTS')
            ->view('mail.status-notification', [
                'recipientName' => $notifiable->name,
                'introText' => 'Se te asignó como líder — ya puedes revisar las evidencias de este ámbito.',
                'statusLabel' => 'Líder asignado',
                'statusBgColor' => $tone['bg'],
                'statusTextColor' => $tone['text'],
                'primaryLabel' => 'Ámbito',
                'primaryValue' => $this->scopeDescription(),
                'contextLabel' => 'Vigente desde',
                'contextValue' => $this->validityDescription(),
                'periodName' => $this->leadership->academicPeriod->name,
                'actionUrl' => route('reviews.index'),
                'actionText' => 'Ver bandeja de revisión',
            ]);
    }

    private function scopeDescription(): string
    {
        return $this->leadership->activity_id
            ? "{$this->leadership->programUnit->name} — {$this->leadership->activity->name}"
            : "Todo el programa: {$this->leadership->programUnit->name}";
    }

    private function validityDescription(): string
    {
        $from = $this->leadership->starts_at->toReadable();

        return $this->leadership->ends_at
            ? "{$from} hasta {$this->leadership->ends_at->toReadable()}"
            : "{$from} (sin fecha de fin)";
    }
}
