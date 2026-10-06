<?php

namespace App\Livewire;

use App\Enums\DeliverableStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ComplianceCalculator;
use App\Services\LeadershipScope;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public ?int $periodFilter = null;

    // Prioridad 6: filtros exclusivos del panel de Coordinación/Administrador
    // — a propósito, sin #[Url] y sin usarse en teacherPanel()/leaderPanel(),
    // que no los exponen (se resuelven con drill-down hacia otras pantallas).
    public ?int $programFilter = null;

    public ?int $teacherFilter = null;

    public ?int $activityFilter = null;

    public ?int $leaderFilter = null;

    public string $evidenceStatusFilter = '';

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');
    }

    private function teacherPanel(User $user): ?array
    {
        if (! $this->periodFilter) {
            return null;
        }

        $evidences = $user->evidences()
            ->with('deliverable')
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->get();

        if ($evidences->isEmpty()) {
            return null;
        }

        $counts = collect(EvidenceStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $evidences->where('status', $status)->count()]);

        $upcoming = $evidences
            ->reject(fn (Evidence $e) => in_array($e->status, [EvidenceStatus::Approved, EvidenceStatus::Exempt], true))
            ->filter(fn (Evidence $e) => $e->deliverable->due_at->between(now(), now()->addDays(14)))
            ->sortBy(fn (Evidence $e) => $e->deliverable->due_at)
            ->values();

        $compliance = ComplianceCalculator::forUser($user, $evidences->pluck('deliverable')->unique('id'), $evidences);

        return compact('counts', 'upcoming', 'compliance');
    }

    /**
     * Antes, por cada docente único bajo el ámbito del líder, se hacían 2
     * consultas nuevas (Deliverable::where(...)->get() +
     * la consulta interna de ComplianceCalculator::forUser()) — con N
     * docentes, 2N consultas. Ahora se traen todos los entregables de las
     * actividades involucradas y todas las evidencias de los docentes
     * involucrados en 2 consultas totales, y el cumplimiento se calcula en
     * PHP sobre esos datos ya cargados.
     */
    private function leaderPanel(User $user): ?array
    {
        if (! $this->periodFilter) {
            return null;
        }

        $leaderships = $user->leaderships()->where('academic_period_id', $this->periodFilter)->get();

        if ($leaderships->isEmpty()) {
            return null;
        }

        $assignments = $leaderships
            ->flatMap(function ($leadership) {
                $query = TeacherAssignment::where('academic_period_id', $this->periodFilter)
                    ->where('program_unit_id', $leadership->program_unit_id);

                if ($leadership->activity_id) {
                    $query->where('activity_id', $leadership->activity_id);
                }

                return $query->with(['user', 'activity.component'])->get();
            })
            ->unique(fn (TeacherAssignment $a) => $a->user_id.'-'.$a->activity_id)
            ->values();

        $deliverablesByActivity = Deliverable::where('academic_period_id', $this->periodFilter)
            ->where('status', DeliverableStatus::Published)
            ->whereIn('activity_id', $assignments->pluck('activity_id')->unique())
            ->get()
            ->groupBy('activity_id');

        $evidencesByUser = Evidence::whereIn('user_id', $assignments->pluck('user_id')->unique())
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->get()
            ->groupBy('user_id');

        $rows = $assignments->map(function (TeacherAssignment $assignment) use ($deliverablesByActivity, $evidencesByUser) {
            $activityDeliverableIds = $deliverablesByActivity->get($assignment->activity_id, collect())->pluck('id');
            $teacherEvidences = $evidencesByUser->get($assignment->user_id, collect());

            return [
                'teacher' => $assignment->user,
                'activity' => $assignment->activity,
                'compliance' => ComplianceCalculator::forUser(
                    $assignment->user,
                    $deliverablesByActivity->get($assignment->activity_id, collect()),
                    $teacherEvidences
                ),
                // Revisión del estado Exento: cuenta por fila (docente +
                // actividad) para poder sumarlas todas abajo sin una
                // consulta nueva — $teacherEvidences puede traer evidencias
                // de OTRAS actividades del mismo docente (no solo la de
                // esta fila), así que se acota a los entregables de
                // $assignment->activity_id antes de contar.
                'exemptCount' => $teacherEvidences
                    ->where('status', EvidenceStatus::Exempt)
                    ->whereIn('deliverable_id', $activityDeliverableIds)
                    ->count(),
            ];
        });

        // onlyViaLeaderRole: true — este contador es "en tu ámbito [de
        // liderazgo]"; no debe sumar compromisos transversales que este
        // usuario pudiera revisar solo porque también es Administrador.
        $pendingReviewCount = Evidence::where('evidences.status', EvidenceStatus::Submitted)
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->reviewableBy($user, onlyViaLeaderRole: true)
            ->count();

        // Prioridad 6: KPIs y gráfico de cumplimiento por actividad, ambos
        // calculados en PHP sobre $rows (ya construido arriba) — ninguna
        // consulta nueva. "% promedio del periodo" es un agregado
        // ponderado (total aprobados / total obligatorios en todo el
        // ámbito), no un promedio simple de los % por fila, para que un
        // docente con muchos obligatorios no pese igual que uno con uno
        // solo.
        $activityCompliance = $rows
            ->groupBy(fn (array $row) => $row['activity']->id)
            ->map(function ($activityRows) {
                $approved = $activityRows->sum(fn (array $row) => $row['compliance']['approved']);
                $total = $activityRows->sum(fn (array $row) => $row['compliance']['total']);
                $activity = $activityRows->first()['activity'];

                return [
                    'activityId' => $activity->id,
                    'label' => $activity->component->name.' — '.$activity->name,
                    'percentage' => $total > 0 ? round($approved / $total * 100, 1) : null,
                    'detail' => $total > 0 ? "{$approved}/{$total}" : null,
                ];
            })
            ->values();

        $approvedOverall = $rows->sum(fn (array $row) => $row['compliance']['approved']);
        $mandatoryOverall = $rows->sum(fn (array $row) => $row['compliance']['total']);

        return [
            'rows' => $rows->values(),
            'pendingReviewCount' => $pendingReviewCount,
            'teacherCount' => $assignments->pluck('user_id')->unique()->count(),
            'averageCompliance' => $mandatoryOverall > 0 ? round($approvedOverall / $mandatoryOverall * 100, 1) : null,
            'activityCompliance' => $activityCompliance,
            'exemptCount' => $rows->sum('exemptCount'),
        ];
    }

    /**
     * Antes, por cada docente activo del sistema, se hacían 2 consultas
     * nuevas (Deliverable::where(...)->get() + la consulta interna de
     * ComplianceCalculator::forUser()) — con N docentes, 2N consultas,
     * recalculadas en cada render() (cada cambio de filtro de periodo).
     * Ahora se traen todos los entregables del periodo (con sus
     * destinatarios) en 1 consulta y se reutiliza $evidences (ya cargada
     * arriba para $counts) agrupada por docente — el cumplimiento se
     * calcula en PHP sobre esos datos, sin consultar de nuevo por docente.
     */
    private function coordinationPanel(): ?array
    {
        if (! $this->periodFilter) {
            return null;
        }

        $statusFilter = $this->evidenceStatusFilter !== '' ? EvidenceStatus::from($this->evidenceStatusFilter) : null;

        // programa/docente/actividad/líder acotan QUÉ DOCENTES entran al
        // panel — se resuelven una sola vez aquí y se reutilizan abajo para
        // $evidences y $upcoming, en vez de repetir cada condición por
        // separado. "líder" reutiliza LeadershipScope (misma vigencia que
        // User::canLeadAssignment()), igual que el filtro equivalente del
        // informe Consolidado.
        $teacherIds = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->when($this->programFilter, fn ($q) => $q->where('program_unit_id', $this->programFilter))
            ->when($this->teacherFilter, fn ($q) => $q->where('id', $this->teacherFilter))
            ->when($this->activityFilter, fn ($q) => $q->whereHas('teacherAssignments', fn ($aq) => $aq
                ->where('academic_period_id', $this->periodFilter)
                ->where('activity_id', $this->activityFilter)
            ))
            ->when($this->leaderFilter, fn ($q) => $q->whereIn('id', LeadershipScope::teacherIdsLedBy(
                User::findOrFail($this->leaderFilter),
                AcademicPeriod::findOrFail($this->periodFilter)
            )))
            ->pluck('id');

        // Sin filtrar por estado: es la base real para ComplianceCalculator
        // más abajo — el % de avance (KPI global, gráfico por programa)
        // nunca debe depender de qué estado se esté mirando en las
        // tarjetas, o perdería su sentido.
        $evidences = Evidence::whereIn('user_id', $teacherIds)
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->get();

        // El filtro de estado sí acota lo que se cuenta/lista (tarjetas KPI
        // por estado), nunca lo que entra a ComplianceCalculator.
        $displayEvidences = $statusFilter ? $evidences->where('status', $statusFilter) : $evidences;

        $counts = collect(EvidenceStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $displayEvidences->where('status', $status)->count()]);

        $evidencesByUser = $evidences->groupBy('user_id');

        $deliverables = Deliverable::where('academic_period_id', $this->periodFilter)
            ->where('status', DeliverableStatus::Published)
            ->when($this->activityFilter, fn ($q) => $q->where('activity_id', $this->activityFilter))
            ->with('recipients')
            ->get();

        // with('programUnit'): única consulta adicional de este bloque —
        // necesaria para la agregación por programa (Prioridad 6), que
        // antes no existía. Sin ella, leer $teacher->programUnit en el
        // map() de abajo dispararía una consulta nueva POR docente.
        $teacherRows = User::whereIn('id', $teacherIds)
            ->with('programUnit')
            ->orderBy('name')
            ->get()
            ->map(function (User $teacher) use ($deliverables, $evidencesByUser) {
                $teacherDeliverables = $deliverables->filter(
                    fn (Deliverable $d) => $d->recipients->contains('id', $teacher->id)
                );

                return [
                    'teacher' => $teacher,
                    'compliance' => ComplianceCalculator::forUser(
                        $teacher,
                        $teacherDeliverables,
                        $evidencesByUser->get($teacher->id, collect())
                    ),
                ];
            })
            ->filter(fn ($row) => $row['compliance']['total'] > 0)
            ->values();

        // Prioridad 6: KPI global y gráfico por programa, agregados en PHP
        // sobre $teacherRows (ya calculado arriba) — mismo criterio
        // ponderado que leaderPanel()'s averageCompliance.
        $approvedOverall = $teacherRows->sum(fn (array $row) => $row['compliance']['approved']);
        $mandatoryOverall = $teacherRows->sum(fn (array $row) => $row['compliance']['total']);
        $globalCompliance = $mandatoryOverall > 0 ? round($approvedOverall / $mandatoryOverall * 100, 1) : null;

        $programCompliance = $teacherRows
            ->groupBy(fn (array $row) => $row['teacher']->program_unit_id ?? 'none')
            ->map(function ($programRows) {
                $approved = $programRows->sum(fn (array $row) => $row['compliance']['approved']);
                $total = $programRows->sum(fn (array $row) => $row['compliance']['total']);
                $programUnit = $programRows->first()['teacher']->programUnit;

                return [
                    'programUnitId' => $programUnit?->id,
                    'label' => $programUnit?->name ?? 'Sin programa asignado',
                    'percentage' => $total > 0 ? round($approved / $total * 100, 1) : null,
                    'detail' => $total > 0 ? "{$approved}/{$total}" : null,
                ];
            })
            ->values();

        $upcoming = Deliverable::where('academic_period_id', $this->periodFilter)
            ->where('status', DeliverableStatus::Published)
            ->when($this->activityFilter, fn ($q) => $q->where('activity_id', $this->activityFilter))
            ->whereBetween('due_at', [now(), now()->addDays(14)])
            ->with(['activity.component', 'crossCuttingCommitment', 'evidences'])
            ->get()
            ->map(function (Deliverable $deliverable) use ($teacherIds, $statusFilter) {
                $relevant = $deliverable->evidences->filter(fn (Evidence $e) => $teacherIds->contains($e->user_id));
                $relevant = $statusFilter ? $relevant->where('status', $statusFilter) : $relevant;

                return [
                    'deliverable' => $deliverable,
                    'total' => $relevant->count(),
                    'pending' => $relevant->whereNotIn('status', [EvidenceStatus::Approved, EvidenceStatus::Exempt])->count(),
                ];
            })
            // Lo que de verdad necesita atención (pendientes reales) va
            // primero; lo ya resuelto (todo enviado) queda después, para
            // que no se diluya entre filas ya cerradas. Dentro de cada
            // grupo, fecha límite ascendente como desempate (como ya
            // estaba antes de este cambio).
            ->sortBy([
                fn ($a, $b) => ($a['pending'] > 0 ? 0 : 1) <=> ($b['pending'] > 0 ? 0 : 1),
                fn ($a, $b) => $a['deliverable']->due_at <=> $b['deliverable']->due_at,
            ])
            ->values();

        return compact('counts', 'teacherRows', 'upcoming', 'globalCompliance', 'programCompliance');
    }

    /**
     * Etiqueta de tiempo restante para una fila de "Próximos vencimientos"
     * (el filtro de 14 días sigue viviendo en teacherPanel()/
     * coordinationPanel(); esto solo formatea, por fila, cuánto falta).
     * Hoy y mañana se marcan como advertencia — mismo tono ámbar que
     * "Requiere ajustes" — porque son los únicos casos donde ya no queda
     * margen real para reaccionar.
     *
     * @return array{label: string, warning: bool}
     */
    public function dueLabel(Carbon $dueAt): array
    {
        $days = (int) now()->startOfDay()->diffInDays($dueAt->copy()->startOfDay());

        return match ($days) {
            0 => ['label' => 'Vence hoy', 'warning' => true],
            1 => ['label' => 'Vence mañana', 'warning' => true],
            default => ['label' => "Vence en {$days} días", 'warning' => false],
        };
    }

    public function render()
    {
        $user = auth()->user();
        $isCoordination = $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor]);

        return view('livewire.dashboard', [
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'teacherPanel' => $user->hasRole(RoleName::Teacher) ? $this->teacherPanel($user) : null,
            'leaderPanel' => $user->hasRole(RoleName::Leader) ? $this->leaderPanel($user) : null,
            'coordinationPanel' => $isCoordination ? $this->coordinationPanel() : null,
            // Opciones de los 5 filtros nuevos del panel de Coordinación —
            // solo se calculan para quien realmente ve ese panel.
            'programUnits' => $isCoordination ? ProgramUnit::orderBy('name')->get() : collect(),
            'filterableTeachers' => $isCoordination
                ? User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))->orderBy('name')->get()
                : collect(),
            'filterableActivities' => $isCoordination ? Activity::with('component')->orderBy('name')->get() : collect(),
            'filterableLeaders' => $isCoordination
                ? User::where('is_active', true)
                    ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Leader->value))
                    ->whereHas('leaderships', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
                    ->orderBy('name')
                    ->get()
                : collect(),
            'evidenceStatusOptions' => EvidenceStatus::cases(),
        ]);
    }
}
