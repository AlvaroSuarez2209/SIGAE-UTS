<div class="max-w-2xl">
    @php $deliverable = $evidence->deliverable; @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">{{ $deliverable->name }}</h1>
        <x-status-badge :status="$evidence->status" />
    </div>

    <div class="card mb-6 space-y-2 p-4 text-sm text-text-secondary">
        @if ($deliverable->description)
            <p>{{ $deliverable->description }}</p>
        @endif
        @if ($deliverable->instructions)
            <p><span class="font-medium text-text-primary">Instrucciones:</span> {{ $deliverable->instructions }}</p>
        @endif
        @if ($deliverable->completion_criteria)
            <p><span class="font-medium text-text-primary">Criterio de cumplimiento:</span> {{ $deliverable->completion_criteria }}</p>
        @endif
        <p><span class="font-medium text-text-primary">Fecha límite:</span> {{ $deliverable->due_at->format('d/m/Y H:i') }}</p>
        @if ($deliverable->closes_at)
            <p><span class="font-medium text-text-primary">Cierre:</span> {{ $deliverable->closes_at->format('d/m/Y H:i') }}</p>
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
            <div>
                <label class="field-label">
                    Archivos
                    <span class="field-help inline">
                        (máx. {{ $deliverable->max_files }}, {{ $deliverable->max_file_size_mb }}MB c/u
                        @if ($deliverable->allowed_file_types)
                            , formatos: {{ collect($deliverable->allowed_file_types)->map(fn ($e) => ".$e")->join(', ') }}
                        @endif
                        )
                    </span>
                </label>

                @if ($evidence->currentVersion && $evidence->currentVersion->files->isNotEmpty())
                    <ul class="mt-2 space-y-1">
                        @foreach ($evidence->currentVersion->files as $file)
                            <li class="flex items-center justify-between rounded-md border border-border-subtle px-3 py-2 text-sm">
                                <a href="{{ route('evidence-files.download', $file) }}" class="flex items-center gap-1.5 text-brand-primary hover:underline">
                                    <x-icon name="paperclip" class="h-4 w-4 shrink-0" />
                                    {{ $file->original_name }}
                                </a>
                                @if ($evidence->status->isEditable())
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 text-sm font-medium text-status-error hover:underline"
                                        @click="$dispatch('confirm-modal', {
                                            title: 'Eliminar archivo',
                                            body: '¿Eliminar el archivo <strong>{{ e($file->original_name) }}</strong>? Esta acción no se puede deshacer.',
                                            confirmLabel: 'Eliminar archivo',
                                            variant: 'danger',
                                            action: () => $wire.removeFile({{ $file->id }}),
                                        })"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                        Quitar
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($evidence->status->isEditable())
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
                            <li class="flex items-center justify-between rounded-md border border-border-subtle px-3 py-2 text-sm">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-brand-primary hover:underline">
                                    <x-icon name="link" class="h-4 w-4 shrink-0" />
                                    {{ $link->label ?: $link->url }}
                                </a>
                                @if ($evidence->status->isEditable())
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 text-sm font-medium text-status-error hover:underline"
                                        @click="$dispatch('confirm-modal', {
                                            title: 'Eliminar enlace',
                                            body: '¿Eliminar el enlace <strong>{{ e($link->label ?: $link->url) }}</strong>? Esta acción no se puede deshacer.',
                                            confirmLabel: 'Eliminar enlace',
                                            variant: 'danger',
                                            action: () => $wire.removeLink({{ $link->id }}),
                                        })"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                        Quitar
                                    </button>
                                @endif
                            </li>
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

    @if ($evidence->reviews->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">Revisiones del líder</h2>
            <ul class="space-y-3">
                @foreach ($evidence->reviews as $review)
                    <li class="card p-3 text-sm">
                        <x-status-badge :status="$review->decision" class="mb-1" />
                        <p class="text-text-secondary">{{ $review->decided_at->format('d/m/Y H:i') }}</p>
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
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">Historial de versiones</h2>
            <ul class="space-y-1 text-sm text-text-secondary">
                @foreach ($evidence->versions as $version)
                    <li>
                        Versión {{ $version->version_number }} —
                        {{ $version->submitted_at ? 'enviada el '.$version->submitted_at->format('d/m/Y H:i') : 'en edición' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
