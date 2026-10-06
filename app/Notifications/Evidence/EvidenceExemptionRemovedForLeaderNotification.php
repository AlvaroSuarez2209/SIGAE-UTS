<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;
use App\Models\Evidence;

/**
 * Al líder (o Coordinación, si nadie lidera ese ámbito ahora mismo): una
 * exención se revirtió — la evidencia vuelve a estar pendiente y puede
 * terminar de nuevo en su bandeja de revisión. Mismo criterio de
 * destinatarios que EvidencePendingReviewNotification
 * (Evidence::reviewerRecipients()). `$previousReason` puede venir vacío
 * si la evidencia quedó exenta antes de que existiera este campo y nunca
 * se recuperó por backfill (ver la migración que agrega
 * `exemption_reason`) — se avisa igual, sin inventar un motivo.
 */
class EvidenceExemptionRemovedForLeaderNotification extends EvidenceStatusNotification
{
    public function __construct(Evidence $evidence, private readonly ?string $previousReason)
    {
        parent::__construct($evidence);
    }

    protected function subject(): string
    {
        return 'Exención retirada — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        $reasonSuffix = $this->previousReason ? " (motivo original: {$this->previousReason})" : '';

        return "Se retiró la exención de {$this->evidence->user->name} para este entregable: vuelve a estar pendiente{$reasonSuffix}.";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Pending->label();
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
