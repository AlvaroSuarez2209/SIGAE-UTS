<div class="mx-auto max-w-2xl">
    <h1 class="mb-6 page-title">
        {{ $template ? 'Editar plantilla' : 'Nueva plantilla' }}
    </h1>

    <form wire:submit="save" class="card space-y-4 p-6">
        <div>
            <label class="field-label">Nombre</label>
            <input type="text" wire:model="name" class="field-input">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Descripción <span class="font-normal text-text-secondary">(opcional)</span></label>
            <textarea wire:model="description" rows="2" class="field-input"></textarea>
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Instrucciones <span class="font-normal text-text-secondary">(opcional)</span></label>
            <textarea wire:model="instructions" rows="3" class="field-input"></textarea>
            @error('instructions') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Criterio de cumplimiento <span class="font-normal text-text-secondary">(opcional)</span></label>
            <textarea wire:model="completion_criteria" rows="2" class="field-input"></textarea>
            @error('completion_criteria') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Enlace de referencia <span class="font-normal text-text-secondary">(opcional)</span></label>
            <p class="field-help">Material adicional, opcional, para entender mejor el entregable.</p>
            <input type="text" wire:model="reference_url" placeholder="https://..." class="field-input">
            @error('reference_url') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="field-label">Archivo de referencia <span class="font-normal text-text-secondary">(opcional)</span></span>
            <p class="field-help">Material adicional, opcional, para entender mejor el entregable.</p>

            @if ($referenceFiles->isNotEmpty())
                <ul class="mt-2 space-y-1">
                    @foreach ($referenceFiles as $file)
                        <li class="flex items-center justify-between gap-3 rounded-md border border-border-subtle px-3 py-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-icon name="paperclip" class="h-4 w-4 shrink-0 text-text-secondary" />
                                <span class="truncate text-base text-text-primary">{{ $file->original_name }}</span>
                                <span class="shrink-0 text-sm text-text-secondary">({{ $file->readable_size }})</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                @if ($file->existsOnDisk())
                                    <a href="{{ route('deliverable-reference-files.download', $file) }}" class="btn-text">Descargar</a>
                                @else
                                    <span class="text-sm font-medium text-status-error">Archivo no disponible</span>
                                @endif
                                <button
                                    type="button"
                                    wire:click="removeReferenceFile({{ $file->id }})"
                                    wire:loading.attr="disabled"
                                    class="flex items-center gap-1 text-sm font-medium text-status-error hover:underline"
                                >
                                    <x-icon name="trash" class="h-4 w-4" />
                                    Quitar
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @elseif ($newReferenceFile)
                <p class="field-help mt-2">{{ $newReferenceFile->getClientOriginalName() }}</p>
            @else
                <x-file-dropzone
                    wire-model="newReferenceFile"
                    accept="{{ collect(\App\Models\Deliverable::DEFAULT_ALLOWED_FILE_TYPES)->map(fn ($e) => '.'.$e)->join(',') }}"
                    class="mt-2"
                />
            @endif
            @error('newReferenceFile') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Tipo de periodicidad</label>
            <select wire:model="periodicity_type" class="field-input">
                @foreach ($periodicityOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('periodicity_type') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="field-label">Tipos de evidencia permitidos</span>
            <div class="mt-2 space-y-1">
                @foreach ($evidenceTypeOptions as $option)
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="checkbox" wire:model="allowed_evidence_types" value="{{ $option->value }}" class="field-checkbox">
                        {{ $option->label() }}
                    </label>
                @endforeach
            </div>
            @error('allowed_evidence_types') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="field-label">Formatos de archivo permitidos <span class="font-normal text-text-secondary">(si aplica)</span></span>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach ($fileTypeOptions as $extension)
                    <label class="flex items-center gap-1 text-sm text-text-secondary">
                        <input type="checkbox" wire:model="allowed_file_types" value="{{ $extension }}" class="field-checkbox">
                        .{{ $extension }}
                    </label>
                @endforeach
            </div>
            @error('allowed_file_types') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label">Máximo de archivos</label>
                <input type="number" min="1" wire:model="max_files" class="field-input">
                @error('max_files') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="field-label">Tamaño máximo (MB)</label>
                <input type="number" min="1" wire:model="max_file_size_mb" class="field-input">
                @error('max_file_size_mb') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="field-label">
                Peso porcentual <span class="font-normal text-text-secondary">(opcional; si se deja en blanco, pesa igual que los demás obligatorios)</span>
            </label>
            <input type="number" min="1" max="100" wire:model="weight_percentage" class="field-input">
            @error('weight_percentage') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_mandatory" id="is_mandatory" class="field-checkbox">
            <label for="is_mandatory" class="text-sm text-text-secondary">Obligatorio</label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active" id="is_active" class="field-checkbox">
            <label for="is_active" class="text-sm text-text-secondary">Activa</label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Guardar</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </button>
            <a href="{{ $this->templatesIndexUrl() }}" class="btn-text text-text-secondary">Cancelar</a>
        </div>
    </form>
</div>
