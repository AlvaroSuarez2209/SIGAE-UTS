<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al docente: su evidencia fue aprobada.
 */
class EvidenceApprovedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Tu evidencia fue aprobada — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return 'Tu evidencia fue aprobada.';
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Approved->label();
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
