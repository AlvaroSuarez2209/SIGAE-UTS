<?php

namespace App\Livewire\Reports;

use App\Models\AcademicPeriod;
use App\Services\Reports\ReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Consolidado por periodo')]
class ConsolidatedReport extends Component
{
    public ?int $periodFilter = null;

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');
    }

    public function render()
    {
        $report = $this->periodFilter
            ? ReportBuilder::consolidated(AcademicPeriod::findOrFail($this->periodFilter))
            : null;

        return view('livewire.reports.consolidated-report', [
            'report' => $report,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
