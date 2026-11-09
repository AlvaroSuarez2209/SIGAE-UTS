<?php

namespace App\Livewire\Reports;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Individual por docente')]
class TeacherReport extends Component
{
    #[Url(as: 'period')]
    public ?int $periodFilter = null;

    #[Url(as: 'teacher')]
    public ?int $teacherFilter = null;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->periodFilter ??= AcademicPeriod::where('status', 'active')->value('id')
            ?? AcademicPeriod::orderByDesc('start_date')->value('id');

        $this->autoselectTeacher();
    }

    /**
     * Punto 12 de la revisión de la directora: al cambiar de periodo, el
     * docente antes seleccionado puede no tener ninguna asignación en el
     * nuevo — se recalcula siempre (no solo si está vacío), y el usuario
     * puede volver a cambiarlo a mano después.
     */
    public function updatedPeriodFilter(): void
    {
        $this->teacherFilter = null;
        $this->autoselectTeacher();
    }

    private function autoselectTeacher(): void
    {
        if ($this->teacherFilter || ! $this->periodFilter) {
            return;
        }

        $this->teacherFilter = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
            ->whereHas('teacherAssignments', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            ->orderByAccentInsensitive('name')
            ->value('id');
    }

    public function render()
    {
        $report = null;

        if ($this->periodFilter && $this->teacherFilter) {
            $report = ReportBuilder::teacher(
                User::findOrFail($this->teacherFilter),
                AcademicPeriod::findOrFail($this->periodFilter),
                $this->statusFilter !== '' ? EvidenceStatus::from($this->statusFilter) : null,
            );
        }

        return view('livewire.reports.teacher-report', [
            'report' => $report,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
            'teachers' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
                ->orderByAccentInsensitive('name')
                ->get(),
            'statusOptions' => EvidenceStatus::cases(),
        ]);
    }
}
