<?php

namespace App\Livewire\Evidence;

use App\Models\AcademicPeriod;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mis entregables')]
class MyDeliverables extends Component
{
    #[Url]
    public ?int $periodFilter = null;

    #[Url(as: 'status')]
    public string $statusFilter = '';

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
