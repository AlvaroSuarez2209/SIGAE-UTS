<?php

namespace App\Observers;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
use App\Models\Deliverable;
use App\Models\TeacherAssignment;

/**
 * Punto 11 de la revisión de la directora: un entregable publicado en
 * modo "todos los docentes de la actividad" (recipient_mode = 'all') solo
 * sincronizaba destinatarios al editar+guardar el formulario — asignar un
 * docente nuevo a esa actividad no actualizaba nada hasta entonces. Suma
 * al docente nuevo como destinatario y le crea su evidencia Pendiente,
 * reutilizando Deliverable::ensureEvidencesForRecipients() (misma
 * notificación, mismo criterio de idempotencia vía wasRecentlyCreated()
 * que ya usa DeliverableForm::save() al publicar).
 *
 * Deliberadamente NO actúa sobre: entregables en modo 'specific', en
 * Borrador, de periodos Cerrados/Archivados, ni compromisos transversales
 * (activity_id nulo, nunca empareja con el activity_id de una asignación).
 * La desactivación o el borrado de asignaciones queda fuera de este lote.
 */
class TeacherAssignmentObserver
{
    public function created(TeacherAssignment $assignment): void
    {
        if (! in_array($assignment->academicPeriod->status, [AcademicPeriodStatus::Planning, AcademicPeriodStatus::Active], true)) {
            return;
        }

        Deliverable::query()
            ->where('activity_id', $assignment->activity_id)
            ->where('academic_period_id', $assignment->academic_period_id)
            ->where('recipient_mode', 'all')
            ->where('status', DeliverableStatus::Published)
            ->get()
            ->each(function (Deliverable $deliverable) use ($assignment) {
                $deliverable->recipients()->syncWithoutDetaching([$assignment->user_id]);
                $deliverable->ensureEvidencesForRecipients([$assignment->user_id]);
            });
    }
}
