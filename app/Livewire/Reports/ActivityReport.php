<?php

namespace App\Livewire\Reports;

use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Services\Reports\ReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Por actividad')]
class ActivityReport extends Component
{
    #[Url(as: 'period')]
    public ?int $periodFilter = null;

    #[Url(as: 'activity')]
    public ?int $activityFilter = null;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->periodFilter ??= AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');

        $this->autoselectActivity();
    }

    /**
     * Punto 12 de la revisión de la directora: misma lógica que
     * TeacherReport::autoselectTeacher() — se recalcula siempre al cambiar
     * de periodo, nunca solo si está vacío.
     */
    public function updatedPeriodFilter(): void
    {
        $this->activityFilter = null;
        $this->autoselectActivity();
    }

    private function autoselectActivity(): void
    {
        if ($this->activityFilter || ! $this->periodFilter) {
            return;
        }

        $this->activityFilter = Activity::whereHas('teacherAssignments', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->orderByAccentInsensitive('name')
            ->value('id');
    }

    public function render()
    {
        $report = null;

        if ($this->periodFilter && $this->activityFilter) {
            $report = ReportBuilder::activity(
                Activity::with('component')->findOrFail($this->activityFilter),
                AcademicPeriod::findOrFail($this->periodFilter),
                $this->statusFilter !== '' ? EvidenceStatus::from($this->statusFilter) : null,
            );
        }

        return view('livewire.reports.activity-report', [
            'report' => $report,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'activities' => Activity::with('component')->orderBy('name')->get(),
            'statusOptions' => EvidenceStatus::cases(),
        ]);
    }
}
