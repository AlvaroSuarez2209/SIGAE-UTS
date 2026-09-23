<?php

namespace App\Notifications\Evidence;

use App\Models\Evidence;
use App\Services\Reports\ReportTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base compartida por las 9 notificaciones de cambio de estado de
 * evidencias: arma el toMail() genérico sobre el layout
 * resources/views/mail/status-notification.blade.php (logo, badge de
 * estado, contexto y botón de acción), y cada subclase solo aporta el
 * texto y la ruta propios de su transición. Mismo principio que
 * ReportTheme para PDF/Excel: un único lugar de identidad visual en vez
 * de repetirla en 9 archivos.
 *
 * SerializesModels es necesario porque $evidence viaja en el payload de
 * la cola (driver `database`): sin él, PHP serializaría la entidad y sus
 * relaciones cargadas tal cual, en vez de guardar solo su ID y
 * recargarla fresca al procesar el job.
 */
abstract class EvidenceStatusNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(protected Evidence $evidence) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deliverable = $this->evidence->deliverable;
        $tone = ReportTheme::statusTone($this->statusLabel())
            ?? ['bg' => ReportTheme::SURFACE_MUTED, 'text' => ReportTheme::SECONDARY];

        return (new MailMessage)
            ->subject($this->subject())
            ->view('mail.status-notification', [
                'recipientName' => $notifiable->name,
                'introText' => $this->introText($notifiable),
                'statusLabel' => $this->statusLabel(),
                'statusBgColor' => $tone['bg'],
                'statusTextColor' => $tone['text'],
                'deliverableName' => $deliverable->name,
                'contextLabel' => $deliverable->isCrossCutting() ? 'Compromiso transversal' : 'Actividad',
                'contextValue' => $deliverable->isCrossCutting()
                    ? $deliverable->crossCuttingCommitment->name
                    : $deliverable->activity->name,
                'periodName' => $deliverable->academicPeriod->name,
                'actionUrl' => $this->actionUrl($notifiable),
                'actionText' => $this->actionText($notifiable),
            ]);
    }

    abstract protected function subject(): string;

    abstract protected function introText(object $notifiable): string;

    abstract protected function statusLabel(): string;

    abstract protected function actionUrl(object $notifiable): string;

    abstract protected function actionText(object $notifiable): string;
}
