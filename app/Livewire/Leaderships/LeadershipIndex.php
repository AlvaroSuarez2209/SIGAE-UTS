<?php

namespace App\Livewire\Leaderships;

use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AcademicPeriod;
use App\Models\Leadership;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Líderes')]
class LeadershipIndex extends Component
{
    use HasStandardPagination, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $periodFilter = null;

    /**
     * Mismo bug/fix que ReviewInbox/DeliverableIndex: mount() pisaba
     * siempre $periodFilter con el periodo activo — "Todos los
     * periodos" nunca sobrevivía a un "Volver" ni a una recarga.
     */
    public function mount(): void
    {
        if (! request()->has('periodFilter')) {
            $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
        }
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
                ->whereHas('user', fn ($q) => $q->whereAccentInsensitive('name', $this->search))
            )
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByDesc('starts_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.leaderships.leadership-index', [
            'leaderships' => $leaderships,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
