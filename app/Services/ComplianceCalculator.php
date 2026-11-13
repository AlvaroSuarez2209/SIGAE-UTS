<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Calcula el % de avance de un docente sobre un conjunto de entregables.
 *
 * Fórmula exacta (cuadro de seguimiento, punto 11):
 *
 * Denominador ("total"): los entregables de $deliverables con
 * is_mandatory = true, MENOS los que tengan una evidencia Exenta (ver
 * más abajo) — nunca Activity::is_mandatory, solo Deliverable::is_mandatory
 * (ver test_activity_is_mandatory_flag_is_never_read_by_the_calculator),
 * y nunca las horas asignadas (TeacherAssignment::assigned_hours es
 * puramente informativo en los informes, jamás entra aquí).
 *
 * Numerador ("approved"): de ese denominador, cuántos tienen una
 * evidencia con status === EvidenceStatus::Approved.
 *
 * Tratamiento por estado de evidencia (EvidenceStatus):
 * - Pending, Draft, Submitted, NeedsAdjustment, Expired: cuentan en el
 *   denominador, nunca en el numerador — "todavía no cumplido", sin
 *   distinción entre ellos para este cálculo. Sin ninguna evidencia
 *   todavía se trata igual que Pending.
 * - Approved: la única condición que satisface el numerador.
 * - Exempt: se excluye del cálculo por completo, igual que un entregable
 *   opcional — no suma ni al numerador ni al denominador. Bug real
 *   encontrado y corregido en la revisión de Prioridad 6: antes, un
 *   entregable exento seguía contando como "no aprobado" en el
 *   denominador, bajando el % de avance del docente exactamente igual
 *   que uno sin resolver — lo opuesto de lo que una exención debería
 *   significar.
 *
 * Si el denominador queda en 0 (sin entregables obligatorios, o todos
 * excluidos por exención) el resultado es percentage = null, NUNCA 0 —
 * "nada que medir" es distinto de "0% de avance".
 *
 * Ponderación (weight_percentage, Deliverable/DeliverableTemplate): SÍ
 * existe y SÍ se usa. Si TODOS los entregables del denominador tienen un
 * weight_percentage no nulo, percentage = peso ganado / peso total * 100
 * (promedio ponderado); si ALGUNO no lo tiene (incluyendo "ninguno lo
 * tiene"), se ignora el peso de todos y se usa conteo simple
 * (aprobados / total) — un esquema de pesos a medias nunca se prorratea.
 *
 * Todo llamador (Dashboard::teacherPanel()/leaderPanel()/coordinationPanel(),
 * ReportBuilder::teacher()/activity()/consolidated()) debe pasar aquí
 * únicamente los entregables de los que el docente es destinatario real
 * (Deliverable::recipients()) — nunca "todo lo publicado en la
 * actividad/periodo" sin ese filtro, o el % deja de coincidir entre
 * paneles/informes para el mismo ámbito (bug real: leaderPanel() y
 * ReportBuilder::activity() no filtraban por recipients(), corregido en
 * el cuadro de seguimiento, punto 11 — ver ComplianceCrossCheckTest).
 */
class ComplianceCalculator
{
    /**
     * @param  Collection<int, Deliverable>  $deliverables
     * @param  Collection<int, Evidence>|null  $evidences  Evidencias de $user ya cargadas
     *                                                     (cualquier subconjunto que incluya las de $deliverables sirve) — evita una consulta
     *                                                     nueva por cada llamada cuando el caller ya las tiene (ver Dashboard::coordinationPanel()/
     *                                                     leaderPanel(), que antes disparaban una consulta aquí por cada docente listado).
     *                                                     Si se omite, se consulta como antes.
     * @return array{percentage: ?float, approved: int, total: int}
     */
    public static function forUser(User $user, Collection $deliverables, ?Collection $evidences = null): array
    {
        $mandatory = $deliverables->where('is_mandatory', true);

        if ($mandatory->isEmpty()) {
            return ['percentage' => null, 'approved' => 0, 'total' => 0];
        }

        $evidenceByDeliverable = ($evidences ?? $user->evidences()->whereIn('deliverable_id', $mandatory->pluck('id'))->get())
            ->keyBy('deliverable_id');

        // Exento se excluye por completo, igual que un opcional — no entra
        // ni al numerador ni al denominador (ver docblock de la clase).
        $mandatory = $mandatory->reject(
            fn (Deliverable $d) => $evidenceByDeliverable->get($d->id)?->status === EvidenceStatus::Exempt
        );

        if ($mandatory->isEmpty()) {
            return ['percentage' => null, 'approved' => 0, 'total' => 0];
        }

        $isApproved = fn (Deliverable $deliverable) => $evidenceByDeliverable
            ->get($deliverable->id)
            ?->status === EvidenceStatus::Approved;

        $approvedCount = $mandatory->filter($isApproved)->count();

        $allWeighted = $mandatory->every(fn (Deliverable $d) => $d->weight_percentage !== null);

        if ($allWeighted) {
            $totalWeight = $mandatory->sum('weight_percentage');
            $earnedWeight = $mandatory->filter($isApproved)->sum('weight_percentage');
            $percentage = $totalWeight > 0 ? round($earnedWeight / $totalWeight * 100, 1) : 0.0;
        } else {
            $percentage = round($approvedCount / $mandatory->count() * 100, 1);
        }

        return [
            'percentage' => $percentage,
            'approved' => $approvedCount,
            'total' => $mandatory->count(),
        ];
    }
}
