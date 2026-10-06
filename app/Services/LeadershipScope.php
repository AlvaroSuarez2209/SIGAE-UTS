<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * IDs de los docentes bajo el ámbito de liderazgo vigente de un líder en un
 * periodo — misma noción de vigencia que User::canLeadAssignment()
 * (starts_at <= now <= ends_at o sin fin), aplicada "al revés": en vez de
 * responder "¿puede este líder revisar ESTA asignación puntual?", resuelve
 * "¿qué asignaciones caen bajo alguno de los liderazgos vigentes de este
 * líder?". Usado por el filtro "líder" del Dashboard (Coordinación) y del
 * informe Consolidado (Prioridad 6) — antes de esto, cada uno habría
 * reimplementado la misma lógica de vigencia por separado.
 */
class LeadershipScope
{
    public static function teacherIdsLedBy(User $leader, AcademicPeriod $period): Collection
    {
        $leaderships = $leader->leaderships()
            ->where('academic_period_id', $period->id)
            ->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->get();

        return $leaderships
            ->flatMap(function ($leadership) use ($period) {
                $query = TeacherAssignment::where('academic_period_id', $period->id)
                    ->where('program_unit_id', $leadership->program_unit_id);

                if ($leadership->activity_id) {
                    $query->where('activity_id', $leadership->activity_id);
                }

                return $query->pluck('user_id');
            })
            ->unique()
            ->values();
    }
}
