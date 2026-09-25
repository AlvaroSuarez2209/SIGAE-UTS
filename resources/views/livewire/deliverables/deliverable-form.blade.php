<div class="mx-auto max-w-2xl">
    <h1 class="mb-6 page-title">
        {{ $deliverable ? 'Editar entregable' : 'Nuevo entregable' }}
    </h1>

    @if ($periodLocked)
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-warning-subtle p-4 text-sm text-status-warning">
            <x-icon name="alert-triangle" class="h-4 w-4 shrink-0" />
            Este periodo está cerrado o archivado. El entregable se muestra en modo de solo consulta.
        </div>
    @endif

    <form wire:submit="save" class="card p-6">
        {{-- Vinculación --}}
        <div class="space-y-4">
            <h2 class="form-section-title">Vinculación</h2>

            @if (! $deliverable && $templates->isNotEmpty())
                <div>
                    <label class="field-label">
                        Cargar desde plantilla <span class="font-normal text-text-secondary">(opcional)</span>
                    </label>
                    <select wire:model.live="template_id" class="field-input">
                        <option value="">Sin plantilla — llenar manualmente</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <span class="field-label">¿A qué está ligado este entregable?</span>
                <div class="mt-2 flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="radio" name="scope_type" wire:model.live="scope_type" value="activity" class="field-radio" @if ($periodLocked) disabled @endif>
                        Una actividad de la distribución
                    </label>
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="radio" name="scope_type" wire:model.live="scope_type" value="cross_cutting" class="field-radio" @if ($periodLocked) disabled @endif>
                        Compromiso transversal (sin actividad)
                    </label>
                </div>
            </div>

            <div>
                <label class="field-label">Periodo académico</label>
                <select wire:model.live="academic_period_id" class="field-input" @if ($periodLocked) disabled @endif>
                    <option value="">Selecciona un periodo</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                    @endforeach
                </select>
                @error('academic_period_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            @if ($scope_type === 'activity')
                <div>
                    <label class="field-label">Actividad</label>
                    <select wire:model.live="activity_id" class="field-input" @if ($periodLocked) disabled @endif>
                        <option value="">Selecciona una actividad</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">
                                {{ $activity->component->name }}@if ($activity->subcomponent) / {{ $activity->subcomponent->name }}@endif
                                — {{ $activity->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('activity_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="field-label">Clasificación del compromiso</label>
                    <select wire:model="cross_cutting_commitment_id" class="field-input" @if ($periodLocked) disabled @endif>
                        <option value="">Selecciona una clasificación</option>
                        @foreach ($commitments as $commitment)
                            <option value="{{ $commitment->id }}">{{ $commitment->name }}</option>
                        @endforeach
                    </select>
                    @error('cross_cutting_commitment_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        {{-- Información general --}}
        <div class="form-section">
            <h2 class="form-section-title">Información general</h2>

            <div>
                <label class="field-label">Nombre</label>
                <input type="text" wire:model="name" class="field-input" @if ($periodLocked) disabled @endif>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="field-label">Descripción <span class="font-normal text-text-secondary">(opcional)</span></label>
                <textarea wire:model="description" rows="2" class="field-input" @if ($periodLocked) disabled @endif></textarea>
            </div>

            <div>
                <label class="field-label">Instrucciones <span class="font-normal text-text-secondary">(opcional)</span></label>
                <textarea wire:model="instructions" rows="3" class="field-input" @if ($periodLocked) disabled @endif></textarea>
            </div>

            <div>
                <label class="field-label">Criterio de cumplimiento <span class="font-normal text-text-secondary">(opcional)</span></label>
                <textarea wire:model="completion_criteria" rows="2" class="field-input" @if ($periodLocked) disabled @endif></textarea>
            </div>
        </div>

        {{-- Programación --}}
        <div class="form-section">
            <h2 class="form-section-title">Programación</h2>

            <div>
                <label class="field-label">Tipo de periodicidad</label>
                <select wire:model="periodicity_type" class="field-input" @if ($periodLocked) disabled @endif>
                    @foreach ($periodicityOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="field-label">Apertura</label>
                    <input type="datetime-local" wire:model="opens_at" class="field-input" @if ($periodLocked) disabled @endif>
                    @error('opens_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">Fecha límite</label>
                    <input type="datetime-local" wire:model="due_at" class="field-input" @if ($periodLocked) disabled @endif>
                    @error('due_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">Cierre <span class="font-normal text-text-secondary">(opc.)</span></label>
                    <input type="datetime-local" wire:model="closes_at" class="field-input" @if ($periodLocked) disabled @endif>
                    @error('closes_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Configuración de evidencia --}}
        <div class="form-section">
            <h2 class="form-section-title">Configuración de evidencia</h2>

            <div>
                <span class="field-label">Tipos de evidencia permitidos</span>
                <div class="mt-2 space-y-1">
                    @foreach ($evidenceTypeOptions as $option)
                        <label class="flex items-center gap-2 text-sm text-text-secondary">
                            <input type="checkbox" wire:model="allowed_evidence_types" value="{{ $option->value }}" class="field-checkbox" @if ($periodLocked) disabled @endif>
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
                            <input type="checkbox" wire:model="allowed_file_types" value="{{ $extension }}" class="field-checkbox" @if ($periodLocked) disabled @endif>
                            .{{ $extension }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Máximo de archivos</label>
                    <input type="number" min="1" wire:model="max_files" class="field-input" @if ($periodLocked) disabled @endif>
                </div>
                <div>
                    <label class="field-label">Tamaño máximo (MB)</label>
                    <input type="number" min="1" wire:model="max_file_size_mb" class="field-input" @if ($periodLocked) disabled @endif>
                </div>
            </div>
        </div>

        {{-- Evaluación --}}
        <div class="form-section">
            <h2 class="form-section-title">Evaluación</h2>

            <div>
                <label class="field-label">
                    Peso porcentual <span class="font-normal text-text-secondary">(opcional; si se deja en blanco, pesa igual que los demás obligatorios)</span>
                </label>
                <div class="relative mt-1">
                    <input type="number" min="1" max="100" wire:model="weight_percentage" class="field-input mt-0 pr-8" @if ($periodLocked) disabled @endif>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-text-secondary">%</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_mandatory" id="is_mandatory" class="field-checkbox" @if ($periodLocked) disabled @endif>
                <label for="is_mandatory" class="text-sm text-text-secondary">Obligatorio (afecta el % de avance)</label>
            </div>
        </div>

        {{-- Destinatarios --}}
        <div class="form-section">
            <h2 class="form-section-title">Destinatarios</h2>

            <div>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="radio" name="recipient_mode" wire:model.live="recipient_mode" value="all" class="field-radio" @if ($periodLocked) disabled @endif>
                        Todos los docentes {{ $scope_type === 'activity' ? 'de la actividad' : 'activos' }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="radio" name="recipient_mode" wire:model.live="recipient_mode" value="subset" class="field-radio" @if ($periodLocked) disabled @endif>
                        Seleccionar docentes específicos
                    </label>
                </div>

                @if ($recipient_mode === 'subset')
                    <div class="mt-3 max-h-48 space-y-1 overflow-y-auto rounded-md border border-border-subtle p-3">
                        @forelse ($candidateTeachers as $teacher)
                            <label class="flex items-center gap-2 text-sm text-text-secondary">
                                <input type="checkbox" wire:model="recipient_ids" value="{{ $teacher->id }}" class="field-checkbox" @if ($periodLocked) disabled @endif>
                                {{ $teacher->name }}
                            </label>
                        @empty
                            <p class="text-sm text-text-secondary">
                                @if ($scope_type === 'activity')
                                    No hay docentes asignados a esta actividad en este periodo todavía.
                                @else
                                    No hay docentes disponibles.
                                @endif
                            </p>
                        @endforelse
                    </div>
                    @error('recipient_ids') <p class="field-error">{{ $message }}</p> @enderror
                @else
                    <p class="field-help">
                        Se incluirán los {{ $candidateTeachers->count() }} docente(s) que cumplen este ámbito al momento de guardar.
                    </p>
                @endif
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            @unless ($periodLocked)
                <button type="submit" class="btn-primary">
                    Guardar
                </button>
            @endunless
            <a href="{{ route('deliverables.index') }}" class="btn-text text-text-secondary">
                {{ $periodLocked ? 'Volver' : 'Cancelar' }}
            </a>
        </div>
    </form>
</div>
