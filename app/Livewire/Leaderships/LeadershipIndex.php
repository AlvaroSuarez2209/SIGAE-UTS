<?php

namespace App\Livewire\Leaderships;

use App\Models\AcademicPeriod;
use App\Models\Leadership;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class LeadershipIndex extends Component
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

    public function endNow(Leadership $leadership): void
    {
        if (! $leadership->ends_at) {
            $leadership->update(['ends_at' => now()->toDateString()]);
        }
    }

    public function render()
    {
        $leaderships = Leadership::query()
            ->with(['user', 'activity.component', 'programUnit', 'academicPeriod'])
            ->when($this->search, fn ($query) => $query
                ->whereHas('user', fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            )
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByDesc('starts_at')
            ->paginate(15);

        return view('livewire.leaderships.leadership-index', [
            'leaderships' => $leaderships,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
