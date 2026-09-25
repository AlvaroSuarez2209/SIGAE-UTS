<?php

namespace App\Livewire\Reviews;

use App\Enums\EvidenceStatus;
use App\Models\Evidence;
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
        // dueño, un líder solo dentro de su ámbito, Administrador solo
        // para compromisos transversales — ver Evidence::isReviewableBy()).
        // Ver tu propia evidencia ya está cubierto por /my-deliverables.
        //
        // La página también sirve reopen() (reabrir una ya aprobada,
        // exclusivo de Administrador sin importar el ámbito de liderazgo)
        // — sin este segundo chequeo, un Administrador que solo puede
        // reabrir (no revisar, porque la evidencia es de una actividad
        // fuera de cualquier ámbito de liderazgo) se quedaría sin poder
        // ni siquiera entrar a la página.
        if (Gate::denies('review', $evidence) && Gate::denies('reopen', $evidence)) {
            Gate::authorize('review', $evidence);
        }

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

        $this->evidence->approveCurrentReview(auth()->user(), $this->observation ?: null);

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

        $this->evidence->returnCurrentReviewForAdjustment(auth()->user(), $this->observation);

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

    public function reopen(): void
    {
        // EvidencePolicy::reopen() ya exige Administrador + estado
        // Approved — no hace falta repetir el chequeo de estado aquí.
        Gate::authorize('reopen', $this->evidence);

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

        return view('livewire.reviews.review-show')
            ->title('Revisar: '.$this->evidence->deliverable->name);
    }
}
