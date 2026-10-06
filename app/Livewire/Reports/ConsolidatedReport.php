<?php

namespace App\Livewire\Reports;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\ProgramUnit;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Consolidado por periodo')]
class ConsolidatedReport extends Component
{
    #[Url(as: 'period')]
    public ?int $periodFilter = null;

    #[Url(as: 'program')]
    public ?int $programFilter = null;

    #[Url(as: 'teacher')]
    public ?int $teacherFilter = null;

    #[Url(as: 'activity')]
    public ?int $activityFilter = null;

    #[Url(as: 'leader')]
    public ?int $leaderFilter = null;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->periodFilter ??= AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');
    }

    public function render()
    {
        $report = $this->periodFilter
            ? ReportBuilder::consolidated(
                AcademicPeriod::findOrFail($this->periodFilter),
                $this->programFilter ? ProgramUnit::find($this->programFilter) : null,
                $this->teacherFilter ? User::find($this->teacherFilter) : null,
                $this->activityFilter ? Activity::find($this->activityFilter) : null,
                $this->leaderFilter ? User::find($this->leaderFilter) : null,
                $this->statusFilter !== '' ? EvidenceStatus::from($this->statusFilter) : null,
            )
            : null;

        return view('livewire.reports.consolidated-report', [
            'report' => $report,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'programUnits' => ProgramUnit::orderBy('name')->get(),
            'teachers' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
                ->orderBy('name')
                ->get(),
            'activities' => Activity::with('component')->orderBy('name')->get(),
            'leaders' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Leader->value))
                ->orderBy('name')
                ->get(),
            'statusOptions' => EvidenceStatus::cases(),
        ]);
    }
}
