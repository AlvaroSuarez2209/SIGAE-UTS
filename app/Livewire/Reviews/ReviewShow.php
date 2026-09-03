<?php

namespace App\Livewire\Reviews;

use App\Enums\EvidenceStatus;
use App\Enums\ReviewDecision;
use App\Models\Evidence;
use App\Models\Review;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ReviewShow extends Component
{
    public Evidence $evidence;

    public string $observation = '';

    public function mount(Evidence $evidence): void
    {
        // El módulo /reviews es la bandeja del revisor: solo entra quien
        // realmente puede decidir sobre esta evidencia (nunca su propio
        // dueño, y un líder solo dentro de su ámbito). Ver tu propia
        // evidencia ya está cubierto por /my-deliverables.
        Gate::authorize('review', $evidence);

        $this->evidence = $evidence;
    }

    public function approve(): void
    {
        Gate::authorize('review', $this->evidence);

        if (! $this->isStillPendingReview()) {
            return;
        }

        $this->validate([
            'observation' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->recordDecision(ReviewDecision::Approved);

        $this->evidence->update(['status' => EvidenceStatus::Approved]);

        session()->flash('status', 'Evidencia aprobada.');
        $this->redirect(route('reviews.index'), navigate: false);
    }

    public function returnForAdjustment(): void
    {
        Gate::authorize('review', $this->evidence);

        if (! $this->isStillPendingReview()) {
            return;
        }

        $this->validate([
            'observation' => ['required', 'string', 'max:5000'],
        ], [
            'observation.required' => 'La devolución exige una observación explicando qué debe ajustar el docente.',
        ]);

        $this->recordDecision(ReviewDecision::Returned);

        $this->evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        session()->flash('status', 'Evidencia devuelta al docente para ajustes.');
        $this->redirect(route('reviews.index'), navigate: false);
    }

    private function isStillPendingReview(): bool
    {
        if ($this->evidence->status !== EvidenceStatus::Submitted) {
            $this->addError('observation', 'Esta evidencia ya no está pendiente de revisión.');

            return false;
        }

        return true;
    }

    private function recordDecision(ReviewDecision $decision): void
    {
        $review = Review::create([
            'evidence_version_id' => $this->evidence->current_version_id,
            'reviewer_id' => auth()->id(),
            'decision' => $decision,
            'decided_at' => now(),
        ]);

        if (filled($this->observation)) {
            $review->observations()->create(['body' => $this->observation]);
        }
    }

    public function reopen(): void
    {
        Gate::authorize('reopen', $this->evidence);

        if ($this->evidence->status !== EvidenceStatus::Approved) {
            return;
        }

        // Excepcional: reabrir una evidencia aprobada. Debe quedar
        // registrado en la bitácora de auditoría cuando el módulo 10 esté
        // implementado (igual que la reapertura de un periodo cerrado).
        $this->evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        session()->flash('status', 'Evidencia reabierta para que el docente pueda ajustarla.');
    }

    public function render()
    {
        $this->evidence->load([
            'deliverable.activity.component',
            'deliverable.crossCuttingCommitment',
            'currentVersion.files',
            'currentVersion.links',
            'reviews.reviewer',
            'reviews.observations',
        ]);

        return view('livewire.reviews.review-show');
    }
}
