<?php

namespace App\Livewire;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ComplianceCalculator;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public ?int $periodFilter = null;

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
            ->whereIn('activity_id', $assignments->pluck('activity_id')->unique())
            ->get()
            ->groupBy('activity_id');

        $evidencesByUser = Evidence::whereIn('user_id', $assignments->pluck('user_id')->unique())
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->get()
            ->groupBy('user_id');

        $rows = $assignments->map(fn (TeacherAssignment $assignment) => [
            'teacher' => $assignment->user,
            'activity' => $assignment->activity,
            'compliance' => ComplianceCalculator::forUser(
                $assignment->user,
                $deliverablesByActivity->get($assignment->activity_id, collect()),
                $evidencesByUser->get($assignment->user_id, collect())
            ),
        ]);

        $pendingReviewCount = Evidence::where('evidences.status', EvidenceStatus::Submitted)
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->reviewableBy($user)
            ->count();

        return ['rows' => $rows->values(), 'pendingReviewCount' => $pendingReviewCount];
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

        $evidences = Evidence::whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))->get();

        $counts = collect(EvidenceStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $evidences->where('status', $status)->count()]);

        $evidencesByUser = $evidences->groupBy('user_id');

        $deliverables = Deliverable::where('academic_period_id', $this->periodFilter)
            ->with('recipients')
            ->get();

        $teacherRows = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
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

        $upcoming = Deliverable::where('academic_period_id', $this->periodFilter)
            ->whereBetween('due_at', [now(), now()->addDays(14)])
            ->with(['activity.component', 'crossCuttingCommitment', 'evidences'])
            ->orderBy('due_at')
            ->get()
            ->map(fn (Deliverable $deliverable) => [
                'deliverable' => $deliverable,
                'total' => $deliverable->evidences->count(),
                'pending' => $deliverable->evidences
                    ->whereNotIn('status', [EvidenceStatus::Approved, EvidenceStatus::Exempt])
                    ->count(),
            ]);

        return compact('counts', 'teacherRows', 'upcoming');
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

        return view('livewire.dashboard', [
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'teacherPanel' => $user->hasRole(RoleName::Teacher) ? $this->teacherPanel($user) : null,
            'leaderPanel' => $user->hasRole(RoleName::Leader) ? $this->leaderPanel($user) : null,
            'coordinationPanel' => $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor])
                ? $this->coordinationPanel()
                : null,
        ]);
    }
}
