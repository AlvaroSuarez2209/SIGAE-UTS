<?php

namespace App\Livewire\Deliverables;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DeliverableIndex extends Component
{
    use WithPagination;

    public ?int $periodFilter = null;

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
    }

    public function updatingPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $deliverables = Deliverable::query()
            ->with(['academicPeriod', 'activity.component', 'crossCuttingCommitment', 'recipients'])
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByDesc('due_at')
            ->paginate(15);

        return view('livewire.deliverables.deliverable-index', [
            'deliverables' => $deliverables,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
