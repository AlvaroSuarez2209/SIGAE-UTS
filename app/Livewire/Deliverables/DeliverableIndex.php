<?php

namespace App\Livewire\Deliverables;

use App\Enums\DeliverableStatus;
use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Entregables')]
class DeliverableIndex extends Component
{
    use HasStandardPagination, WithPagination;

    #[Url]
    public ?int $periodFilter = null;

    /**
     * Mismo bug/fix que ReviewInbox (commit 285f3a3): mount() pisaba
     * siempre $periodFilter con el periodo activo, sin importar lo que
     * viniera en la URL — "Todos los periodos" nunca sobrevivía a un
     * "Volver" ni a una recarga. Solo se aplica el valor por defecto
     * cuando el query string ni siquiera trae la clave.
     */
    public function mount(): void
    {
        if (! request()->has('periodFilter')) {
            $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
        }
    }

    public function updatingPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Los borradores van primero (son los que de verdad necesitan
        // atención — decidir si se publican o se descartan); los
        // publicados van después. Dentro de cada grupo, más reciente
        // primero por fecha de creación del registro — mismo criterio
        // para los dos grupos, así que un solo orderByDesc('created_at')
        // basta como desempate una vez resuelto el orden entre grupos.
        $deliverables = Deliverable::query()
            ->with(['academicPeriod', 'activity.component', 'crossCuttingCommitment', 'recipients'])
            ->when($this->periodFilter, fn ($query) => $query->where('academic_period_id', $this->periodFilter))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [DeliverableStatus::Draft->value])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.deliverables.deliverable-index', [
            'deliverables' => $deliverables,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
