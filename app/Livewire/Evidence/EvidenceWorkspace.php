<?php

namespace App\Livewire\Evidence;

use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\EvidenceFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class EvidenceWorkspace extends Component
{
    use WithFileUploads;

    public Evidence $evidence;

    public string $description = '';

    public array $newFiles = [];

    public string $newLinkUrl = '';

    public string $newLinkLabel = '';

    public string $submissionError = '';

    public string $exemptionJustification = '';

    public function mount(Evidence $evidence): void
    {
        Gate::authorize('view', $evidence);

        $this->evidence = $evidence;

        $draft = $evidence->currentVersion && $evidence->currentVersion->submitted_at === null
            ? $evidence->currentVersion
            : null;

        $this->description = $draft?->description ?? '';
    }

    private function allowedTypes(): array
    {
        return $this->evidence->deliverable->allowed_evidence_types->map->value->all();
    }

    private function fileValidationRules(): array
    {
        $deliverable = $this->evidence->deliverable;
        $rules = ['file', 'max:'.($deliverable->max_file_size_mb * 1024)];

        if (! empty($deliverable->allowed_file_types)) {
            $rules[] = 'mimes:'.implode(',', $deliverable->allowed_file_types);
        }

        return $rules;
    }

    public function saveDraft(): bool
    {
        Gate::authorize('update', $this->evidence);

        $this->submissionError = '';

        if (! $this->evidence->status->isEditable()) {
            $this->submissionError = 'Esta evidencia ya fue enviada y no admite ediciones directas.';

            return false;
        }

        $deliverable = $this->evidence->deliverable;
        $allowed = $this->allowedTypes();

        $rules = [];

        if (in_array(EvidenceType::Text->value, $allowed, true)) {
            $rules['description'] = ['nullable', 'string', 'max:10000'];
        }

        if (! empty($this->newFiles)) {
            $existingCount = $this->evidence->currentVersion?->files()->count() ?? 0;
            if ($existingCount + count($this->newFiles) > $deliverable->max_files) {
                $this->addError('newFiles', "Este entregable admite máximo {$deliverable->max_files} archivo(s).");

                return false;
            }

            $rules['newFiles.*'] = $this->fileValidationRules();
        }

        if (! empty($rules)) {
            $this->validate($rules);
        }

        $version = $this->evidence->startOrGetDraftVersion(auth()->user());

        if (array_key_exists('description', $rules)) {
            $version->update(['description' => $this->description ?: null]);
        }

        foreach ($this->newFiles as $file) {
            $storedPath = $file->store('evidence/'.$version->id, 'local');

            EvidenceFile::create([
                'evidence_version_id' => $version->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($storedPath),
                'disk_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $this->newFiles = [];
        $this->evidence->refresh();

        session()->flash('status', 'Borrador guardado.');

        return true;
    }

    public function addLink(): void
    {
        Gate::authorize('update', $this->evidence);

        if (! $this->evidence->status->isEditable()) {
            return;
        }

        $this->validate([
            'newLinkUrl' => ['required', 'url', 'max:2048'],
            'newLinkLabel' => ['nullable', 'string', 'max:255'],
        ]);

        $version = $this->evidence->startOrGetDraftVersion(auth()->user());

        $version->links()->create([
            'url' => $this->newLinkUrl,
            'label' => $this->newLinkLabel ?: null,
        ]);

        $this->newLinkUrl = '';
        $this->newLinkLabel = '';
        $this->evidence->refresh();
    }

    public function removeFile(EvidenceFile $file): void
    {
        Gate::authorize('update', $this->evidence);

        if (! $this->evidence->status->isEditable() || $file->evidence_version_id !== $this->evidence->current_version_id) {
            return;
        }

        $file->disk()->delete($file->disk_path);
        $file->delete();
        $this->evidence->refresh();
    }

    public function removeLink(int $linkId): void
    {
        Gate::authorize('update', $this->evidence);

        $link = $this->evidence->currentVersion?->links()->find($linkId);

        if (! $link || ! $this->evidence->status->isEditable()) {
            return;
        }

        $link->delete();
        $this->evidence->refresh();
    }

    public function submit(): void
    {
        $this->submissionError = '';

        if (! $this->saveDraft()) {
            return;
        }

        $version = $this->evidence->currentVersion;
        $allowed = $this->allowedTypes();

        $hasFile = $version->files()->exists();
        $hasText = filled($version->description);
        $hasLink = $version->links()->exists();

        $satisfiesFile = in_array(EvidenceType::File->value, $allowed, true) || in_array(EvidenceType::MultipleFiles->value, $allowed, true);
        $satisfiesText = in_array(EvidenceType::Text->value, $allowed, true);
        $satisfiesLink = in_array(EvidenceType::Link->value, $allowed, true);

        $meetsMinimum = ($satisfiesFile && $hasFile) || ($satisfiesText && $hasText) || ($satisfiesLink && $hasLink);

        if (! $meetsMinimum) {
            $this->submissionError = 'Debes adjuntar al menos una evidencia (archivo, texto o enlace, según lo permitido) antes de enviar.';

            return;
        }

        $this->evidence->submitCurrentVersion();
        $this->evidence->refresh();

        session()->flash('status', 'Evidencia enviada correctamente.');
    }

    public function markExempt(): void
    {
        Gate::authorize('markExempt', $this->evidence);

        $this->validate([
            'exemptionJustification' => ['required', 'string', 'max:2000'],
        ], [
            'exemptionJustification.required' => 'La exención exige una justificación explicando por qué el docente queda eximido.',
        ]);

        $this->evidence->markExempt($this->exemptionJustification);
        $this->exemptionJustification = '';
        $this->evidence->refresh();

        session()->flash('status', 'Evidencia marcada como exenta.');
    }

    public function removeExemption(): void
    {
        Gate::authorize('removeExemption', $this->evidence);

        $this->evidence->removeExemption();
        $this->evidence->refresh();

        session()->flash('status', 'Exención retirada; la evidencia vuelve a estar pendiente.');
    }

    public function render()
    {
        $this->evidence->load([
            'deliverable.activity.component',
            'deliverable.crossCuttingCommitment',
            'currentVersion.files',
            'currentVersion.links',
            'versions',
            'reviews.reviewer',
            'reviews.observations',
        ]);

        return view('livewire.evidence.evidence-workspace', [
            'allowed' => $this->allowedTypes(),
        ]);
    }
}
