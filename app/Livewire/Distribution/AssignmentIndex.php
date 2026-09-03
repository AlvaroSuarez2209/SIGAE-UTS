<?php

namespace App\Livewire\Distribution;

use App\Models\AcademicPeriod;
use App\Models\TeacherAssignment;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AssignmentIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $periodFilter = null;

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $assignments = TeacherAssignment::query()
            ->with(['user', 'academicPeriod', 'activity.component', 'activity.subcomponent', 'programUnit'])
            ->when($this->search, fn ($query) => $query
                ->whereHas('user', fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            )
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.distribution.assignment-index', [
            'assignments' => $assignments,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
