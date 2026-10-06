<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al docente: su evidencia fue marcada como exenta.
 */
class EvidenceExemptedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Tu evidencia fue marcada como exenta — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        $reason = $this->evidence->exemption_reason;
        $reasonSuffix = $reason ? " Motivo: {$reason}" : '';

        return "Tu evidencia fue marcada como exenta: ya no cuenta como pendiente en tu avance.{$reasonSuffix}";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Exempt->label();
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
