<?php

namespace App\Livewire\Reports;

use App\Models\AcademicPeriod;
use App\Models\CrossCuttingCommitment;
use App\Services\Reports\ReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CrossCuttingReport extends Component
{
    public ?int $periodFilter = null;

    public string $commitmentFilter = '';

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');
    }

    public function render()
    {
        $report = null;

        if ($this->periodFilter) {
            $report = ReportBuilder::crossCutting(
                AcademicPeriod::findOrFail($this->periodFilter),
                $this->commitmentFilter ? CrossCuttingCommitment::find($this->commitmentFilter) : null
            );
        }

        return view('livewire.reports.cross-cutting-report', [
            'report' => $report,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'commitments' => CrossCuttingCommitment::orderBy('name')->get(),
        ]);
    }
}
