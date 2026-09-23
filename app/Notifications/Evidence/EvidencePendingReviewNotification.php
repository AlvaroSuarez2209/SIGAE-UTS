<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al líder (o Coordinación, si nadie lidera ese ámbito ahora mismo):
 * llegó una evidencia nueva que requiere su revisión.
 */
class EvidencePendingReviewNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Evidencia pendiente de revisión — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return "{$this->evidence->user->name} envió una evidencia que está pendiente de tu revisión.";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Submitted->label();
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('reviews.show', $this->evidence);
    }

    protected function actionText(object $notifiable): string
    {
        return 'Revisar evidencia';
    }
}
