<?php

namespace App\Livewire\Deliverables;

use App\Enums\DeliverableStatus;
use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Entregables')]
class DeliverableIndex extends Component
{
    use HasStandardPagination, WithPagination;

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
        // Los borradores van primero (son los que de verdad necesitan
        // atención — decidir si se publican o se descartan), ordenados
        // entre sí por más reciente primero; los publicados van después,
        // ordenados entre sí por fecha límite ascendente. Las 2 llamadas
        // orderByRaw() definen cada criterio de ordenamiento dentro de su
        // propio grupo (el CASE vale NULL fuera de ese grupo, así que
        // nunca interfiere con el orden entre grupos, ya resuelto por la
        // primera llamada); orderBy('due_at') al final solo decide el
        // orden dentro del grupo de publicados.
        $deliverables = Deliverable::query()
            ->with(['academicPeriod', 'activity.component', 'crossCuttingCommitment', 'recipients'])
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [DeliverableStatus::Draft->value])
            ->orderByRaw('CASE WHEN status = ? THEN created_at END DESC', [DeliverableStatus::Draft->value])
            ->orderBy('due_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.deliverables.deliverable-index', [
            'deliverables' => $deliverables,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
