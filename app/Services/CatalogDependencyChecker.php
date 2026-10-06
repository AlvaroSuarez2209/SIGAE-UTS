<?php

namespace App\Services;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\DeliverableTemplate;
use App\Models\ProgramUnit;
use App\Models\Subcomponent;

/**
 * Cuántos dependientes "activos" bloquean la desactivación de un catálogo —
 * Prioridad 4 de la revisión de la directora. Entregable y Asignación
 * docente no tienen su propia columna is_active (son filas fechadas, no
 * catálogos con interruptor): "activo" para ellos se define como
 * pertenecer a un periodo académico que no esté Cerrado ni Archivado — un
 * entregable de un periodo ya cerrado no debería bloquear nada. Liderazgo
 * usa su propio criterio de vigencia (starts_at/ends_at), el mismo que ya
 * usa User::canLeadAssignment(). Un entregable en Borrador tampoco cuenta
 * como dependiente "activo" — no es visible ni accionable para ningún
 * docente todavía, así que no debería impedir desactivar su actividad,
 * compromiso transversal o plantilla (revisión de la directora, punto 2).
 */
class CatalogDependencyChecker
{
    private const OPEN_PERIOD_STATUSES = [AcademicPeriodStatus::Planning, AcademicPeriodStatus::Active];

    public static function activeSubcomponentCount(Component $component): int
    {
        return $component->subcomponents()->where('is_active', true)->count();
    }

    /**
     * Actividades adscritas directamente al componente (con o sin
     * subcomponente) — Activity::component_id nunca es nulo, así que esto
     * ya cubre las que no tienen subcomponente asignado.
     */
    public static function activeActivityCountForComponent(Component $component): int
    {
        return $component->activities()->where('is_active', true)->count();
    }

    public static function activeActivityCountForSubcomponent(Subcomponent $subcomponent): int
    {
        return $subcomponent->activities()->where('is_active', true)->count();
    }

    public static function openDeliverableCountForActivity(Activity $activity): int
    {
        return $activity->deliverables()
            ->where('status', DeliverableStatus::Published)
            ->whereHas('academicPeriod', fn ($q) => $q->whereIn('status', self::OPEN_PERIOD_STATUSES))
            ->count();
    }

    public static function openTeacherAssignmentCountForActivity(Activity $activity): int
    {
        return $activity->teacherAssignments()
            ->whereHas('academicPeriod', fn ($q) => $q->whereIn('status', self::OPEN_PERIOD_STATUSES))
            ->count();
    }

    public static function openTeacherAssignmentCountForProgramUnit(ProgramUnit $programUnit): int
    {
        return $programUnit->teacherAssignments()
            ->whereHas('academicPeriod', fn ($q) => $q->whereIn('status', self::OPEN_PERIOD_STATUSES))
            ->count();
    }

    public static function activeLeadershipCountForProgramUnit(ProgramUnit $programUnit): int
    {
        return $programUnit->leaderships()
            ->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->count();
    }

    public static function openDeliverableCountForCommitment(CrossCuttingCommitment $commitment): int
    {
        return $commitment->deliverables()
            ->where('status', DeliverableStatus::Published)
            ->whereHas('academicPeriod', fn ($q) => $q->whereIn('status', self::OPEN_PERIOD_STATUSES))
            ->count();
    }

    public static function openDeliverableCountForTemplate(DeliverableTemplate $template): int
    {
        return $template->deliverables()
            ->where('status', DeliverableStatus::Published)
            ->whereHas('academicPeriod', fn ($q) => $q->whereIn('status', self::OPEN_PERIOD_STATUSES))
            ->count();
    }
}
