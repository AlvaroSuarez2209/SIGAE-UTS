<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Collection;

/**
 * Agrupa actividades por componente (para <optgroup>) y construye la
 * etiqueta "Subcomponente / Actividad" (o solo el nombre de la
 * actividad si no tiene subcomponente) — punto único usado por los 5
 * selectores de actividad del sistema (DeliverableForm, AssignmentForm,
 * ActivityReport, ConsolidatedReport, Dashboard) para que todos se vean
 * y ordenen igual. Asume que $activities ya viene ordenada por
 * Activity::orderedByHierarchy() — aquí solo agrupa, no reordena nada
 * (groupBy() conserva el orden en que cada grupo aparece por primera
 * vez en la colección de origen).
 */
class ActivitySelectOptions
{
    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, array{component: string, options: Collection<int, array{id: int, label: string}>}>
     */
    public static function grouped(Collection $activities): Collection
    {
        return $activities
            ->groupBy(fn (Activity $activity) => $activity->component_id)
            ->map(fn (Collection $group) => [
                'component' => $group->first()->component->name,
                'options' => $group->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'label' => self::optionLabel($activity),
                ])->values(),
            ])
            ->values();
    }

    public static function optionLabel(Activity $activity): string
    {
        return $activity->subcomponent
            ? "{$activity->subcomponent->name} / {$activity->name}"
            : $activity->name;
    }
}
