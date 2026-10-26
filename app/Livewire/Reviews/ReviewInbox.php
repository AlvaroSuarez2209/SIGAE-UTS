<?php

namespace App\Livewire\Reviews;

use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Evidence;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisión')]
class ReviewInbox extends Component
{
    #[Url]
    public ?int $periodFilter = null;

    /**
     * El atributo #[Url] ya puebla $periodFilter desde el query string
     * antes de que este mount() se ejecute (incluido el caso explícito
     * "Todos los periodos", que llega como valor vacío/null) — solo se
     * aplica el periodo activo por defecto cuando el query string ni
     * siquiera trae la clave, es decir, en una primera visita sin filtro.
     */
    public function mount(): void
    {
        if (! request()->has('periodFilter')) {
            $this->periodFilter = AcademicPeriod::where('status', 'active')->value('id');
        }
    }

    public function render()
    {
        $pending = Evidence::query()
            ->where('evidences.status', EvidenceStatus::Submitted)
            ->reviewableBy(auth()->user())
            ->with(['deliverable.activity.component', 'deliverable.crossCuttingCommitment', 'user', 'currentVersion'])
            ->when($this->periodFilter, fn ($query) => $query
                ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            )
            ->get()
            ->sortBy(fn (Evidence $evidence) => $evidence->currentVersion?->submitted_at)
            ->values();

        return view('livewire.reviews.review-inbox', [
            'pending' => $pending,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
