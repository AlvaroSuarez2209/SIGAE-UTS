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
 * Regla de negocio central (no negociable): el avance se calcula SIEMPRE
 * sobre entregables obligatorios (con o sin ponderación) y NUNCA sobre las
 * horas asignadas. Si ninguno de los entregables obligatorios del conjunto
 * tiene peso porcentual, todos pesan igual; si todos lo tienen, se usa un
 * promedio ponderado. Un conjunto mixto (algunos con peso, otros sin) se
 * trata como "sin pesos" para no inventar una regla de prorrateo que el
 * negocio no pidió.
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
