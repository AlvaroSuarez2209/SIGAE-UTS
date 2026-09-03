<?php

namespace App\Livewire\Reports;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Services\Reports\ReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ActivityReport extends Component
{
    public ?int $periodFilter = null;

    public ?int $activityFilter = null;

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');
    }

    public function render()
    {
        $report = null;

        if ($this->periodFilter && $this->activityFilter) {
            $report = ReportBuilder::activity(
                Activity::with('component')->findOrFail($this->activityFilter),
                AcademicPeriod::findOrFail($this->periodFilter)
            );
        }

        return view('livewire.reports.activity-report', [
            'report' => $report,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'activities' => Activity::with('component')->orderBy('name')->get(),
        ]);
    }
}
