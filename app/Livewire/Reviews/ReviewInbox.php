<?php

namespace App\Livewire\Reviews;

use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Evidence;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisión')]
class ReviewInbox extends Component
{
    public ?int $periodFilter = null;

    public function mount(): void
    {
        $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
    }

    public function render()
    {
        $pending = Evidence::query()
            ->where('status', EvidenceStatus::Submitted)
            ->with(['deliverable.activity.component', 'deliverable.crossCuttingCommitment', 'user', 'currentVersion'])
            ->when($this->periodFilter, fn ($query) => $query
                ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            )
            ->get()
            ->filter(fn (Evidence $evidence) => $evidence->isReviewableBy(auth()->user()))
            ->sortBy(fn (Evidence $evidence) => $evidence->currentVersion?->submitted_at)
            ->values();

        return view('livewire.reviews.review-inbox', [
            'pending' => $pending,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
