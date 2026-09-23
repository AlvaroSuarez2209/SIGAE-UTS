<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al líder/revisor, confirmando su propia decisión de aprobar la
 * evidencia.
 */
class EvidenceApprovalConfirmedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Confirmación de aprobación — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return "Aprobaste la evidencia de {$this->evidence->user->name}.";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Approved->label();
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('reviews.show', $this->evidence);
    }

    protected function actionText(object $notifiable): string
    {
        return 'Ver evidencia';
    }
}
