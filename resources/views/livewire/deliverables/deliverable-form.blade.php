<div class="mx-auto max-w-2xl">
    <h1 class="mb-6 page-title">
        {{ $deliverable ? 'Editar entregable' : 'Nuevo entregable' }}
    </h1>

    @if ($periodLocked)
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-warning-subtle p-4 text-sm text-status-warning">
            <x-icon name="alert-triangle" class="h-4 w-4 shrink-0" />
            Este periodo está cerrado o archivado. El entregable se muestra en modo de solo consulta.
        </div>

        {{-- Solo consulta: nada es editable, así que no tiene sentido el
             wizard — se muestra todo de corrido, como antes de Prioridad 5. --}}
        <div class="card space-y-6 p-6">
            <div class="space-y-4">
                <h2 class="form-section-title">Vinculación</h2>
                <p><span class="field-label">Periodo académico</span> {{ $periods->firstWhere('id', $academic_period_id)?->name }}</p>
                @if ($scope_type === 'activity')
                    <p><span class="field-label">Actividad</span> {{ $activities->firstWhere('id', $activity_id)?->name }}</p>
                @else
                    <p><span class="field-label">Compromiso transversal</span> {{ $commitments->firstWhere('id', $cross_cutting_commitment_id)?->name }}</p>
                @endif
            </div>

            <div class="space-y-4">
                <h2 class="form-section-title">Información general</h2>
                <p><span class="field-label">Nombre</span> {{ $name }}</p>
                @if ($description)<p><span class="field-label">Descripción</span> {{ $description }}</p>@endif
                @if ($instructions)<p><span class="field-label">Instrucciones</span> {{ $instructions }}</p>@endif
                @if ($completion_criteria)<p><span class="field-label">Criterio de cumplimiento</span> {{ $completion_criteria }}</p>@endif
            </div>

            <div class="space-y-4">
                <h2 class="form-section-title">Programación</h2>
                <p><span class="field-label">Periodicidad</span> {{ collect($periodicityOptions)->first(fn ($o) => $o->value === $periodicity_type)?->label() }}</p>
                <p><span class="field-label">Apertura</span> {{ $opens_at }}</p>
                <p><span class="field-label">Fecha límite</span> {{ $due_at }}</p>
                @if ($closes_at)<p><span class="field-label">Cierre</span> {{ $closes_at }}</p>@endif
            </div>

            <div class="space-y-4">
                <h2 class="form-section-title">Evidencia y evaluación</h2>
                <p><span class="field-label">Tipos de evidencia</span> {{ collect($evidenceTypeOptions)->filter(fn ($o) => in_array($o->value, $allowed_evidence_types))->map->label()->join(', ') }}</p>
                <p><span class="field-label">Peso porcentual</span> {{ $weight_percentage ? $weight_percentage.'%' : 'Igual que los demás obligatorios' }}</p>
                <p><span class="field-label">Obligatorio</span> {{ $is_mandatory ? 'Sí' : 'No' }}</p>
            </div>

            <div class="space-y-4">
                <h2 class="form-section-title">Destinatarios</h2>
                <p>{{ $deliverable->recipients->count() }} docente(s)</p>
            </div>
        </div>

        <div class="mt-6">
            <a href="{{ route('deliverables.index') }}" class="btn-text text-text-secondary">Volver</a>
        </div>
    @else
        {{-- Indicador de pasos --}}
        @php
            $stepLabels = [1 => 'Vinculación', 2 => 'Información', 3 => 'Programación', 4 => 'Evidencia', 5 => 'Destinatarios', 6 => 'Confirmar'];
        @endphp

        {{-- Escritorio/tablet (sm y mayor): grilla con número + etiqueta de
             cada paso, igual que antes. --}}
        <ol class="mb-6 hidden grid-cols-6 gap-1 text-center text-xs sm:grid">
            @foreach ($stepLabels as $n => $label)
                <li>
                    <button
                        type="button"
                        wire:click="goToStep({{ $n }})"
                        @if ($n > $maxStepReached) disabled @endif
                        class="w-full rounded-md border py-2 {{ $step === $n ? 'border-brand-primary bg-brand-primary-subtle font-semibold text-brand-primary' : ($n <= $maxStepReached ? 'border-border-subtle text-text-secondary hover:bg-surface-muted' : 'border-border-subtle text-text-secondary/50 cursor-not-allowed') }}"
                    >
                        <span class="block">{{ $n }}</span>
                        <span class="block">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        {{-- Móvil (debajo de sm): las 6 etiquetas de texto no caben sin
             superponerse en el ancho real disponible (~375-414px), así que
             solo se muestra el paso activo como texto, y el resto de pasos
             como puntos de progreso sin etiqueta. --}}
        <div class="mb-6 sm:hidden">
            <p class="mb-2 text-sm font-medium text-text-primary">
                Paso {{ $step }} de {{ $totalSteps }} — {{ $stepLabels[$step] }}
            </p>
            <div class="flex items-center gap-1">
                @foreach ($stepLabels as $n => $label)
                    <button
                        type="button"
                        wire:click="goToStep({{ $n }})"
                        @if ($n > $maxStepReached) disabled @endif
                        aria-label="Paso {{ $n }} de {{ $totalSteps }}: {{ $label }}"
                        class="flex h-8 w-8 shrink-0 items-center justify-center"
                    >
                        <span class="block h-2.5 w-2.5 rounded-full {{ $step === $n ? 'bg-brand-primary' : ($n <= $maxStepReached ? 'bg-brand-primary/40' : 'bg-border-subtle') }}"></span>
                    </button>
                @endforeach
            </div>
        </div>

        <form wire:submit="save" class="card p-6">
            {{-- Paso 1: Vinculación --}}
            <div class="space-y-4" @unless ($step === 1) style="display:none" @endunless>
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
                            <input type="radio" name="scope_type" wire:model.live="scope_type" value="activity" class="field-radio">
                            Una actividad de la distribución
                        </label>
                        <label class="flex items-center gap-2 text-sm text-text-secondary">
                            <input type="radio" name="scope_type" wire:model.live="scope_type" value="cross_cutting" class="field-radio">
                            Compromiso transversal (sin actividad)
                        </label>
                    </div>
                    @error('scope_type') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="field-label">Periodo académico</label>
                    <select wire:model.live="academic_period_id" class="field-input">
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
                        <select wire:model.live="activity_id" class="field-input">
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
                        <select wire:model="cross_cutting_commitment_id" class="field-input">
                            <option value="">Selecciona una clasificación</option>
                            @foreach ($commitments as $commitment)
                                <option value="{{ $commitment->id }}">{{ $commitment->name }}</option>
                            @endforeach
                        </select>
                        @error('cross_cutting_commitment_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            {{-- Paso 2: Información general --}}
            <div class="space-y-4" @unless ($step === 2) style="display:none" @endunless>
                <h2 class="form-section-title">Información general</h2>

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
            </div>

            {{-- Paso 3: Programación --}}
            <div class="space-y-4" @unless ($step === 3) style="display:none" @endunless>
                <h2 class="form-section-title">Programación</h2>

                <div>
                    <label class="field-label">Tipo de periodicidad</label>
                    <select wire:model="periodicity_type" class="field-input">
                        @foreach ($periodicityOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('periodicity_type') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="field-label">Apertura</label>
                        <input type="datetime-local" wire:model="opens_at" class="field-input">
                        @error('opens_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label">Fecha límite</label>
                        <input type="datetime-local" wire:model="due_at" class="field-input">
                        @error('due_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label">Cierre <span class="font-normal text-text-secondary">(opc.)</span></label>
                        <input type="datetime-local" wire:model="closes_at" class="field-input">
                        @error('closes_at') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Paso 4: Configuración de evidencia y evaluación --}}
            <div class="space-y-4" @unless ($step === 4) style="display:none" @endunless>
                <h2 class="form-section-title">Evidencia y evaluación</h2>

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
                    <div class="relative mt-1">
                        <input type="number" min="1" max="100" wire:model="weight_percentage" class="field-input mt-0 pr-8">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-text-secondary">%</span>
                    </div>
                    @error('weight_percentage') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="is_mandatory" id="is_mandatory" class="field-checkbox">
                    <label for="is_mandatory" class="text-sm text-text-secondary">Obligatorio (afecta el % de avance)</label>
                </div>
            </div>

            {{-- Paso 5: Destinatarios --}}
            <div class="space-y-4" @unless ($step === 5) style="display:none" @endunless>
                <h2 class="form-section-title">Destinatarios</h2>

                <div>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 text-sm text-text-secondary">
                            <input type="radio" name="recipient_mode" wire:model.live="recipient_mode" value="all" class="field-radio">
                            Todos los docentes {{ $scope_type === 'activity' ? 'de la actividad' : 'activos' }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-text-secondary">
                            <input type="radio" name="recipient_mode" wire:model.live="recipient_mode" value="subset" class="field-radio">
                            Seleccionar docentes específicos
                        </label>
                    </div>

                    @if ($recipient_mode === 'subset')
                        <div class="mt-3 max-h-48 space-y-1 overflow-y-auto rounded-md border border-border-subtle p-3">
                            @forelse ($candidateTeachers as $teacher)
                                <label class="flex items-center gap-2 text-sm text-text-secondary">
                                    <input type="checkbox" wire:model="recipient_ids" value="{{ $teacher->id }}" class="field-checkbox">
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
                        @if ($errors->has('recipient_ids'))
                            <p class="field-error">{{ $errors->first('recipient_ids') }}</p>
                        @elseif ($candidateTeachers->isEmpty())
                            <p class="field-help text-status-warning">
                                Todavía no hay ningún docente que cumpla este ámbito. Puedes guardarlo como borrador mientras se resuelve; no podrás publicarlo hasta que haya al menos uno.
                            </p>
                        @else
                            <p class="field-help">
                                Se incluirán los {{ $candidateTeachers->count() }} docente(s) que cumplen este ámbito al momento de guardar.
                            </p>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Paso 6: Vista previa y confirmación --}}
            <div class="space-y-6" @unless ($step === 6) style="display:none" @endunless>
                <div class="flex items-center gap-3">
                    <h2 class="form-section-title">Vista previa y confirmación</h2>
                    @if ($deliverable)
                        <x-status-badge :status="$deliverable->status" />
                    @endif
                </div>
                <p class="field-help">
                    @if ($deliverable && $deliverable->status->value === 'published')
                        Revisa los datos antes de guardar los cambios.
                    @else
                        Revisa los datos antes de publicar. Mientras no haya al menos un destinatario resuelto, solo puedes guardarlo como borrador.
                    @endif
                    Puedes volver a cualquier paso para corregirlo.
                </p>

                <div class="space-y-4 rounded-md border border-border-subtle p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-text-primary">Vinculación</h3>
                            <p class="text-sm text-text-secondary">Periodo: {{ $periods->firstWhere('id', $academic_period_id)?->name ?? '—' }}</p>
                            @if ($scope_type === 'activity')
                                <p class="text-sm text-text-secondary">Actividad: {{ $activities->firstWhere('id', $activity_id)?->name ?? '—' }}</p>
                            @else
                                <p class="text-sm text-text-secondary">Compromiso transversal: {{ $commitments->firstWhere('id', $cross_cutting_commitment_id)?->name ?? '—' }}</p>
                            @endif
                        </div>
                        <button type="button" wire:click="goToStep(1)" class="btn-text shrink-0 text-text-secondary">Editar</button>
                    </div>

                    <div class="flex items-start justify-between gap-4 border-t border-border-subtle pt-4">
                        <div>
                            <h3 class="font-semibold text-text-primary">Información general</h3>
                            <p class="text-sm text-text-secondary">Nombre: {{ $name ?: '—' }}</p>
                            @if ($description)<p class="text-sm text-text-secondary">Descripción: {{ $description }}</p>@endif
                        </div>
                        <button type="button" wire:click="goToStep(2)" class="btn-text shrink-0 text-text-secondary">Editar</button>
                    </div>

                    <div class="flex items-start justify-between gap-4 border-t border-border-subtle pt-4">
                        <div>
                            <h3 class="font-semibold text-text-primary">Programación</h3>
                            <p class="text-sm text-text-secondary">Periodicidad: {{ collect($periodicityOptions)->first(fn ($o) => $o->value === $periodicity_type)?->label() ?? '—' }}</p>
                            <p class="text-sm text-text-secondary">Apertura: {{ $opens_at ?: '—' }} · Fecha límite: {{ $due_at ?: '—' }}@if ($closes_at) · Cierre: {{ $closes_at }}@endif</p>
                        </div>
                        <button type="button" wire:click="goToStep(3)" class="btn-text shrink-0 text-text-secondary">Editar</button>
                    </div>

                    <div class="flex items-start justify-between gap-4 border-t border-border-subtle pt-4">
                        <div>
                            <h3 class="font-semibold text-text-primary">Evidencia y evaluación</h3>
                            <p class="text-sm text-text-secondary">
                                Tipos: {{ collect($evidenceTypeOptions)->filter(fn ($o) => in_array($o->value, $allowed_evidence_types))->map->label()->join(', ') ?: '—' }}
                            </p>
                            <p class="text-sm text-text-secondary">
                                Máx. {{ $max_files }} archivo(s), {{ $max_file_size_mb }}MB c/u
                                @if ($allowed_file_types) · formatos: {{ collect($allowed_file_types)->map(fn ($e) => ".$e")->join(', ') }}@endif
                            </p>
                            <p class="text-sm text-text-secondary">
                                Peso: {{ $weight_percentage ? $weight_percentage.'%' : 'igual que los demás obligatorios' }} · {{ $is_mandatory ? 'Obligatorio' : 'No obligatorio' }}
                            </p>
                        </div>
                        <button type="button" wire:click="goToStep(4)" class="btn-text shrink-0 text-text-secondary">Editar</button>
                    </div>

                    <div class="flex items-start justify-between gap-4 border-t border-border-subtle pt-4">
                        <div>
                            <h3 class="font-semibold text-text-primary">Destinatarios</h3>
                            @if ($recipient_mode === 'all')
                                <p class="text-sm text-text-secondary">Todos los docentes que cumplen el ámbito ({{ $candidateTeachers->count() }} actualmente).</p>
                            @else
                                <p class="text-sm text-text-secondary">
                                    {{ $candidateTeachers->whereIn('id', $recipient_ids)->pluck('name')->join(', ') ?: 'Ningún docente seleccionado.' }}
                                </p>
                            @endif
                        </div>
                        <button type="button" wire:click="goToStep(5)" class="btn-text shrink-0 text-text-secondary">Editar</button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                @if ($step > 1)
                    <button type="button" wire:click="previousStep" class="btn-text text-text-secondary">Atrás</button>
                @endif

                @if ($step < $totalSteps)
                    <button type="button" wire:click="nextStep" class="btn-primary">Siguiente</button>
                @else
                    @if (! $deliverable || $deliverable->status->value === 'draft')
                        <button
                            type="button"
                            wire:click="saveAsDraft"
                            wire:loading.attr="disabled"
                            wire:target="saveAsDraft,save"
                            class="btn-secondary"
                        >
                            <span wire:loading.remove wire:target="saveAsDraft">Guardar como borrador</span>
                            <span wire:loading wire:target="saveAsDraft">Guardando...</span>
                        </button>
                        <button
                            type="button"
                            @click="$dispatch('confirm-modal', {
                                title: 'Publicar entregable',
                                body: 'Vas a publicar este entregable: quedará visible para los docentes destinatarios de inmediato y ya no podrás editarlo libremente, solo hacer ajustes puntuales. ¿Continuar?',
                                confirmLabel: 'Publicar',
                                variant: 'warning',
                                action: () => $wire.save(),
                            })"
                            wire:loading.attr="disabled"
                            wire:target="saveAsDraft,save"
                            class="btn-primary"
                        >
                            <span wire:loading.remove wire:target="save">Publicar</span>
                            <span wire:loading wire:target="save">Publicando...</span>
                        </button>
                    @else
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Guardar cambios</span>
                            <span wire:loading wire:target="save">Guardando...</span>
                        </button>
                    @endif
                @endif

                <a href="{{ route('deliverables.index') }}" class="btn-text text-text-secondary">Cancelar</a>
            </div>
        </form>
    @endif
</div>
