<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al docente, confirmando que su propio envío se registró correctamente.
 */
class EvidenceSubmissionConfirmedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Confirmación de envío — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return 'Tu evidencia fue enviada correctamente y ahora está pendiente de revisión.';
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Submitted->label();
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('my-deliverables.show', $this->evidence);
    }

    protected function actionText(object $notifiable): string
    {
        return 'Ver mi entregable';
    }
}
