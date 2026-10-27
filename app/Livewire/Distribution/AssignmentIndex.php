<?php

namespace App\Livewire\Distribution;

use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AcademicPeriod;
use App\Models\TeacherAssignment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Distribución')]
class AssignmentIndex extends Component
{
    use HasStandardPagination, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $periodFilter = null;

    /**
     * Mismo bug/fix que los demás índices filtrables: mount() pisaba
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

    public function render()
    {
        $assignments = TeacherAssignment::query()
            ->with(['user', 'academicPeriod', 'activity.component', 'activity.subcomponent', 'programUnit'])
            ->when($this->search, fn ($query) => $query
                ->whereHas('user', fn ($q) => $q->whereAccentInsensitive('name', $this->search))
            )
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.distribution.assignment-index', [
            'assignments' => $assignments,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
