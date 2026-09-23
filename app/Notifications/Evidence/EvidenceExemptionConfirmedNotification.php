<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * A quien marcó la exención (Administrador o Coordinación), confirmando
 * su propia acción.
 */
class EvidenceExemptionConfirmedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Confirmación de exención — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return "Marcaste como exenta la evidencia de {$this->evidence->user->name}.";
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
        return 'Ver evidencia';
    }
}
