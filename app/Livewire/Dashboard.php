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

        $compliance = ComplianceCalculator::forUser($user, $evidences->pluck('deliverable')->unique('id'));

        return compact('counts', 'upcoming', 'compliance');
    }

    private function leaderPanel(User $user): ?array
    {
        if (! $this->periodFilter) {
            return null;
        }

        $leaderships = $user->leaderships()->where('academic_period_id', $this->periodFilter)->get();

        if ($leaderships->isEmpty()) {
            return null;
        }

        $rows = collect();

        foreach ($leaderships as $leadership) {
            $query = TeacherAssignment::where('academic_period_id', $this->periodFilter)
                ->where('program_unit_id', $leadership->program_unit_id);

            if ($leadership->activity_id) {
                $query->where('activity_id', $leadership->activity_id);
            }

            $assignments = $query->with(['user', 'activity.component'])->get()
                ->unique(fn (TeacherAssignment $a) => $a->user_id.'-'.$a->activity_id);

            foreach ($assignments as $assignment) {
                $key = $assignment->user_id.'-'.$assignment->activity_id;

                if ($rows->has($key)) {
                    continue;
                }

                $deliverables = Deliverable::where('academic_period_id', $this->periodFilter)
                    ->where('activity_id', $assignment->activity_id)
                    ->get();

                $compliance = ComplianceCalculator::forUser($assignment->user, $deliverables);

                $rows->put($key, [
                    'teacher' => $assignment->user,
                    'activity' => $assignment->activity,
                    'compliance' => $compliance,
                ]);
            }
        }

        $pendingReviewCount = Evidence::where('status', EvidenceStatus::Submitted)
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->get()
            ->filter(fn (Evidence $e) => $e->isReviewableBy($user))
            ->count();

        return ['rows' => $rows->values(), 'pendingReviewCount' => $pendingReviewCount];
    }

    private function coordinationPanel(): ?array
    {
        if (! $this->periodFilter) {
            return null;
        }

        $evidences = Evidence::whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))->get();

        $counts = collect(EvidenceStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $evidences->where('status', $status)->count()]);

        $teacherRows = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->orderBy('name')
            ->get()
            ->map(function (User $teacher) {
                $deliverables = Deliverable::where('academic_period_id', $this->periodFilter)
                    ->whereHas('recipients', fn ($q) => $q->where('user_id', $teacher->id))
                    ->get();

                return [
                    'teacher' => $teacher,
                    'compliance' => ComplianceCalculator::forUser($teacher, $deliverables),
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
