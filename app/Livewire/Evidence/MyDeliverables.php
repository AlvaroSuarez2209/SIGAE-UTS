<?php

namespace App\Livewire\Evidence;

use App\Models\AcademicPeriod;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MyDeliverables extends Component
{
    public ?int $periodFilter = null;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
    }

    public function render()
    {
        $evidences = auth()->user()->evidences()
            ->with(['deliverable.activity.component', 'deliverable.crossCuttingCommitment', 'deliverable.academicPeriod'])
            ->when($this->periodFilter, fn ($query) => $query
                ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            )
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->get()
            ->sortBy('deliverable.due_at');

        $grouped = $evidences->groupBy(fn ($evidence) => $evidence->deliverable->isCrossCutting()
            ? 'Compromisos transversales'
            : $evidence->deliverable->activity->component->name.' — '.$evidence->deliverable->activity->name
        );

        return view('livewire.evidence.my-deliverables', [
            'grouped' => $grouped,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
