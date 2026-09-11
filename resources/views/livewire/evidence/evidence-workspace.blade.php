<div class="mx-auto max-w-2xl">
    @php $deliverable = $evidence->deliverable; @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">{{ $deliverable->name }}</h1>
        <x-status-badge :status="$evidence->status" />
    </div>

    @if ($deliverable->description || $deliverable->instructions || $deliverable->completion_criteria)
        <div class="card mb-6 space-y-2 p-4 text-base text-text-secondary">
            <h2 class="form-section-title mb-2">Instrucciones</h2>
            @if ($deliverable->description)
                <p><span class="font-medium text-text-primary">Descripción:</span> {{ $deliverable->description }}</p>
            @endif
            @if ($deliverable->instructions)
                <p><span class="font-medium text-text-primary">Instrucciones:</span> {{ $deliverable->instructions }}</p>
            @endif
            @if ($deliverable->completion_criteria)
                <p><span class="font-medium text-text-primary">Criterio de cumplimiento:</span> {{ $deliverable->completion_criteria }}</p>
            @endif
        </div>
    @endif

    <div class="card mb-6 space-y-2 p-4 text-base text-text-secondary">
        <p><span class="font-medium text-text-primary">Fecha límite:</span> {{ $deliverable->due_at->toReadable() }}</p>
        @if ($deliverable->closes_at)
            <p><span class="font-medium text-text-primary">Cierre:</span> {{ $deliverable->closes_at->toReadable() }}</p>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-success-subtle p-3 text-sm text-status-success">
            <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
            {{ session('status') }}
        </div>
    @endif

    @if ($submissionError)
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-error-subtle p-3 text-sm text-status-error">
            <x-icon name="alert-circle" class="h-4 w-4 shrink-0" />
            {{ $submissionError }}
        </div>
    @endif

    @if (! $evidence->status->isEditable())
        <div class="mb-4 flex items-center gap-2 rounded-md bg-surface-muted p-4 text-sm text-text-secondary">
            <x-icon name="shield-check" class="h-4 w-4 shrink-0" />
            Esta evidencia está en estado "{{ $evidence->status->label() }}" y no se puede editar directamente.
        </div>
    @endif

    <div class="card space-y-5 p-6">
        @if (in_array('text', $allowed))
            <div>
                <label class="field-label">Texto de la evidencia</label>
                <textarea wire:model="description" rows="5" @disabled(! $evidence->status->isEditable()) class="field-input"></textarea>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @endif

        @if (in_array('file', $allowed) || in_array('multiple_files', $allowed))
            @php $fileCount = $evidence->currentVersion?->files->count() ?? 0; @endphp
            <div>
                <label class="field-label">
                    Archivos
                    <span class="field-help inline">{{ $deliverable->fileConstraintsLabel }}</span>
                </label>

                @if ($fileCount > 0)
                    <ul class="mt-2 space-y-1">
                        @foreach ($evidence->currentVersion->files as $file)
                            <x-evidence-file-row :file="$file" :removable="$evidence->status->isEditable()" />
                        @endforeach
                    </ul>
                @endif

                @if ($evidence->status->isEditable())
                    @if ($fileCount < $deliverable->max_files)
                        <div
                            x-data="{ isDragging: false, uploading: false, progress: 0 }"
                            x-on:livewire-upload-start="uploading = true"
                            x-on:livewire-upload-finish="uploading = false; progress = 0"
                            x-on:livewire-upload-error="uploading = false"
                            x-on:livewire-upload-progress="progress = $event.detail.progress"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="isDragging = false; $refs.newFilesInput.files = $event.dataTransfer.files; $refs.newFilesInput.dispatchEvent(new Event('change'))"
                            :class="isDragging ? 'border-brand-primary bg-brand-primary-subtle' : 'border-border-subtle'"
                            class="mt-2 rounded-md border-2 border-dashed p-6 text-center transition-colors"
                        >
                            <input type="file" x-ref="newFilesInput" wire:model="newFiles" multiple id="newFiles" class="sr-only">
                            <label for="newFiles" class="flex cursor-pointer flex-col items-center gap-1.5">
                                <x-icon name="paperclip" class="h-6 w-6 text-text-secondary" />
                                <span class="text-sm text-text-secondary">
                                    Arrastra los archivos aquí o <span class="font-medium text-brand-primary">haz clic para seleccionar</span>
                                </span>
                            </label>

                            <div x-show="uploading" x-cloak class="mx-auto mt-3 max-w-xs">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
                                    <div class="h-full bg-brand-primary transition-all" :style="`width: ${progress}%`"></div>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="field-help">
                            Ya alcanzaste el máximo de {{ $deliverable->max_files }} archivo(s) permitido(s). Quita el actual para poder subir uno nuevo.
                        </p>
                    @endif
                    @error('newFiles') <p class="field-error">{{ $message }}</p> @enderror
                    @error('newFiles.*') <p class="field-error">{{ $message }}</p> @enderror
                @endif
            </div>
        @endif

        @if (in_array('link', $allowed))
            <div>
                <label class="field-label">Enlaces</label>

                @if ($evidence->currentVersion && $evidence->currentVersion->links->isNotEmpty())
                    <ul class="mt-2 space-y-1">
                        @foreach ($evidence->currentVersion->links as $link)
                            <x-evidence-link-row :link="$link" :removable="$evidence->status->isEditable()" />
                        @endforeach
                    </ul>
                @endif

                @if ($evidence->status->isEditable())
                    <div class="mt-2 flex gap-2">
                        <input type="url" wire:model="newLinkUrl" placeholder="https://..." class="field-input mt-0">
                        <input type="text" wire:model="newLinkLabel" placeholder="Etiqueta (opcional)" class="field-input mt-0 w-48">
                        <button type="button" wire:click="addLink" class="btn-secondary whitespace-nowrap">Agregar</button>
                    </div>
                    @error('newLinkUrl') <p class="field-error">{{ $message }}</p> @enderror
                @endif
            </div>
        @endif

        @if ($evidence->status->isEditable())
            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="saveDraft" class="btn-secondary">
                    Guardar borrador
                </button>
                <button
                    type="button"
                    class="btn-primary"
                    @click="$dispatch('confirm-modal', {
                        title: 'Enviar evidencia',
                        body: '¿Confirmas el envío? Una vez enviado no podrás editar esta evidencia directamente.',
                        confirmLabel: 'Enviar evidencia',
                        variant: 'primary',
                        action: () => $wire.submit(),
                    })"
                >
                    Enviar evidencia
                </button>
                <a href="{{ route('my-deliverables.index') }}" class="btn-text">Volver</a>
            </div>
        @else
            <a href="{{ route('my-deliverables.index') }}" class="btn-text inline-block">Volver</a>
        @endif
    </div>

    @can('markExempt', $evidence)
        <div class="card mt-6 space-y-4 p-6">
            <h2 class="form-section-title">Exención (Administración/Coordinación)</h2>
            <p class="text-base text-text-secondary">
                Eximir a este docente de este entregable: deja de estar obligado a enviarlo y ya no cuenta en su % de avance.
            </p>
            <div>
                <label class="field-label">
                    Justificación <span class="font-normal text-text-secondary">(obligatoria)</span>
                </label>
                <textarea wire:model="exemptionJustification" rows="3" class="field-input"></textarea>
                @error('exemptionJustification') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button
                type="button"
                class="btn-warning"
                @click="$dispatch('confirm-modal', {
                    title: 'Marcar evidencia como exenta',
                    body: '¿Marcar esta evidencia como exenta? El docente ya no tendrá que enviarla y dejará de contar en su porcentaje de avance. Esta acción quedará registrada en la auditoría.',
                    confirmLabel: 'Marcar como exento',
                    variant: 'warning',
                    action: () => $wire.markExempt(),
                })"
            >
                <x-icon name="shield-check" class="h-4 w-4" />
                Marcar como exento
            </button>
        </div>
    @endcan

    @can('removeExemption', $evidence)
        <div class="card mt-6 space-y-4 p-6">
            <h2 class="form-section-title">Exención activa</h2>
            <p class="text-base text-text-secondary">
                Esta evidencia está marcada como exenta. El docente no puede editarla ni enviarla mientras la exención esté activa.
            </p>
            <button
                type="button"
                class="btn-secondary"
                @click="$dispatch('confirm-modal', {
                    title: 'Quitar exención',
                    body: '¿Quitar la exención de esta evidencia? Volverá al estado Pendiente y, si la fecha límite ya pasó, podrá marcarse Vencida nuevamente.',
                    confirmLabel: 'Quitar exención',
                    variant: 'warning',
                    action: () => $wire.removeExemption(),
                })"
            >
                Quitar exención
            </button>
        </div>
    @endcan

    @if ($evidence->reviews->isNotEmpty())
        <div class="mt-6">
            <h2 class="form-section-title mb-2">Revisiones del líder</h2>
            <ul class="space-y-3">
                @foreach ($evidence->reviews as $review)
                    <li class="card p-3 text-base">
                        <x-status-badge :status="$review->decision" class="mb-1" />
                        <p class="text-text-secondary">{{ $review->decided_at->toReadable() }}</p>
                        @foreach ($review->observations as $observation)
                            <p class="mt-1 text-text-secondary">{{ $observation->body }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($evidence->versions->count() > 1)
        <div class="mt-6">
            <h2 class="form-section-title mb-2">Historial de versiones</h2>
            <ul class="space-y-1 text-base text-text-secondary">
                @foreach ($evidence->versions as $version)
                    <li>
                        Versión {{ $version->version_number }} —
                        {{ $version->submitted_at ? 'enviada el '.$version->submitted_at->toReadable() : 'en edición' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
