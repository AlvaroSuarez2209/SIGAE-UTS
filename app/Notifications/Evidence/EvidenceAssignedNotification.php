<?php

namespace App\Notifications\Evidence;

use App\Enums\EvidenceStatus;

/**
 * Al docente, cuando se le asigna un entregable nuevo — antes, publicar
 * un entregable (o agregar a alguien como destinatario de uno ya
 * publicado) no avisaba nada: el docente solo se enteraba si entraba a
 * "Mis entregables" por su cuenta. Disparada desde
 * Deliverable::ensureEvidencesForRecipients(), solo cuando la Evidence
 * es realmente nueva (`wasRecentlyCreated`) — nunca en un re-guardado
 * del mismo entregable para alguien que ya era destinatario.
 */
class EvidenceAssignedNotification extends EvidenceStatusNotification
{
    protected function subject(): string
    {
        return 'Nuevo entregable asignado — SIGAE-UTS';
    }

    protected function introText(object $notifiable): string
    {
        return 'Se te asignó un nuevo entregable para cumplir. Revisa la fecha límite y lo que debes adjuntar.';
    }

    protected function statusLabel(): string
    {
        return EvidenceStatus::Pending->label();
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('my-deliverables.show', $this->evidence);
    }

    protected function actionText(object $notifiable): string
    {
        return 'Ver entregable';
    }
}
