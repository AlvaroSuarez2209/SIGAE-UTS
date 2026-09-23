<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al docente: su evidencia fue devuelta y debe ajustarla.
 */
class EvidenceNeedsAdjustmentNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Tu evidencia requiere ajustes — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return 'Tu evidencia fue devuelta: revisa las observaciones del líder y vuelve a enviarla.';
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::NeedsAdjustment->label();
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('my-deliverables.show', $this->evidence);
    }

    protected function actionText(object $notifiable): string
    {
        return 'Ajustar evidencia';
    }
}
