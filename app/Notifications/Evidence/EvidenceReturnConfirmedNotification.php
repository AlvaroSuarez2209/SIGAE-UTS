<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al líder/revisor, confirmando su propia decisión de devolver la
 * evidencia al docente.
 */
class EvidenceReturnConfirmedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Confirmación de devolución — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return "Devolviste la evidencia de {$this->evidence->user->name} para que la ajuste.";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::NeedsAdjustment->label();
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
