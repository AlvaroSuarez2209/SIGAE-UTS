<?php

namespace App\Livewire\Reviews;

use App\Enums\EvidenceStatus;
use App\Models\Evidence;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ReviewShow extends Component
{
    public Evidence $evidence;

    public string $observation = '';

    /**
     * Bug "el filtro de periodo se pierde al pulsar Volver": nunca es
     * esta pantalla la que decide su valor (a diferencia de
     * ReviewInbox::$periodFilter) — solo lo recibe por la URL (puesto
     * ahí por el enlace "Revisar" de la bandeja) y lo reenvía tal cual en
     * "Volver" y en cada redirect de vuelta a la bandeja, para que el
     * filtro sobreviva el viaje de ida y vuelta. #[Url] (no
     * request()->query() a mano) es necesario para que siga disponible
     * también dentro de approve()/returnForAdjustment(), que llegan en
     * una petición AJAX de Livewire distinta de la carga inicial de la
     * página.
     */
    #[Url]
    public ?int $periodFilter = null;

    /**
     * "Todos los periodos" se representa como null — y route() omite de
     * la URL cualquier parámetro en null, indistinguible entonces de "no
     * venía ningún filtro". ReviewInbox::mount() solo respeta un filtro
     * ausente-pero-vacío (`request()->has('periodFilter')` en vez de
     * comparar contra null) — para que eso funcione, hay que forzar la
     * clave a estar SIEMPRE presente en la URL, aunque su valor sea una
     * cadena vacía (`?periodFilter=`, no la ausencia del parámetro).
     */
    public function reviewsIndexUrl(): string
    {
        return route('reviews.index', ['periodFilter' => $this->periodFilter ?? '']);
    }

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

        // Nada que mostrar todavía: Evidence::isReviewableBy() no mira el
        // estado (un líder/Administrador pasa el chequeo de arriba para
        // CUALQUIER estado dentro de su ámbito), así que se puede llegar
        // aquí con una evidencia Pendiente que nunca tuvo ninguna versión
        // (current_version_id nulo — a diferencia de Borrador, que sí
        // tiene una versión real sin enviar, y para la que esta pantalla
        // ya renderiza sin problema). La vista asume $evidence->currentVersion
        // para mostrar fecha de envío, contenido, archivos y enlaces, y
        // truena con un 500 en vez de mostrar algo con sentido. No cambia
        // ningún permiso: a quien ya pasó el chequeo de arriba solo se le
        // redirige a la bandeja con un mensaje, nunca se le niega el
        // acceso que ya tenía.
        if (! $evidence->currentVersion) {
            session()->flash('error', 'Esta evidencia todavía no tiene ninguna versión enviada; no hay nada que revisar.');
            $this->redirect($this->reviewsIndexUrl(), navigate: false);

            return;
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
        $this->redirect($this->reviewsIndexUrl(), navigate: false);
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
        $this->redirect($this->reviewsIndexUrl(), navigate: false);
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
            'deliverable.academicPeriod',
            'deliverable.activity.component',
            'deliverable.crossCuttingCommitment',
            'currentVersion.files',
            'currentVersion.links',
            'versions.files',
            'reviews.reviewer',
            'reviews.observations',
            'reviews.version',
        ]);

        return view('livewire.reviews.review-show', [
            'referenceFiles' => $this->evidence->deliverable->activeReferenceFiles()->get(),
        ])->title('Revisar: '.$this->evidence->deliverable->name);
    }
}
