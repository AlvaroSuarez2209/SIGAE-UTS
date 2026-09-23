<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Doble destinatario: al docente (venció su propia evidencia) y a
 * Coordinación (seguimiento institucional) — misma clase, el texto y el
 * enlace se adaptan según quién la reciba, porque un docente no tiene
 * acceso a /reviews.
 */
class EvidenceOverdueNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Evidencia vencida — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        if ($notifiable->id === $this->evidence->user_id) {
            return 'Tu evidencia venció: la fecha límite ya pasó sin que la enviaras.';
        }

        return "La evidencia de {$this->evidence->user->name} venció: la fecha límite ya pasó sin que la enviara.";
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Expired->label();
    }

    protected function actionUrl(object $notifiable): string
    {
        return $notifiable->id === $this->evidence->user_id
            ? route('my-deliverables.show', $this->evidence)
            : route('reviews.show', $this->evidence);
    }

    protected function actionText(object $notifiable): string
    {
        return $notifiable->id === $this->evidence->user_id ? 'Ver mi entregable' : 'Ver evidencia';
    }
}
