<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;
use App\Models\Evidence;

/**
 * Al líder (o Coordinación, si nadie lidera ese ámbito ahora mismo): una
 * evidencia que tenía o podía tener pendiente de revisar quedó exenta —
 * ya no debe esperarla. Mismo criterio de destinatarios que
 * EvidencePendingReviewNotification (Evidence::reviewerRecipients()).
 */
class EvidenceExemptedForLeaderNotification extends EvidenceStatusNotification
{
    public function __construct(Evidence $evidence, private readonly string $reason)
    {
        parent::__construct($evidence);
    }

    protected function subject(): string
    {
        return 'Evidencia marcada como exenta — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return "La evidencia de {$this->evidence->user->name} quedó exenta — ya no está obligado a presentarla. Motivo: {$this->reason}";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Exempt->label();
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
