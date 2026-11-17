<div class="mx-auto max-w-5xl">
    @php
        $deliverable = $evidence->deliverable;
        $version = $evidence->currentVersion;
        $isCrossCutting = $deliverable->isCrossCutting();
        $assignment = $evidence->matchingTeacherAssignment();
        $lateDays = $deliverable->lateSubmissionDays($version->submitted_at);
        $reviewContext = $this->reviewContextLabel();
        $sameDueAndClose = $deliverable->closes_at && $deliverable->due_at->eq($deliverable->closes_at);
    @endphp

    {{--
        Ajustes al rediseño visual, punto 1: "Volver" pasa a botón
        secundario con ícono, en la esquina superior derecha en
        escritorio / encima del título en móvil — mismo truco order-N/
        lg:order-none que el resto del layout.
    --}}
    <div class="mb-2 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="order-2 flex flex-wrap items-center gap-3 lg:order-none">
            <h1 class="page-title">{{ $deliverable->name }}</h1>
            <x-status-badge :status="$evidence->status" class="text-sm" />
        </div>
        <a href="{{ $this->reviewsIndexUrl() }}" class="btn-secondary order-1 w-fit lg:order-none">
            <x-icon name="chevron-left" class="h-4 w-4" />
            Volver
        </a>
    </div>

    {{--
        Ajustes al rediseño visual, punto 3: dos grupos (Contexto,
        Etiquetas) con más separación entre grupos (gap-x-6) que dentro
        de cada uno (gap-x-3), en tamaño de párrafo (text-base).
    --}}
    <div class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-base text-text-secondary">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="inline-flex items-center gap-1 font-medium text-text-primary">
                <x-icon name="user" class="h-4 w-4 shrink-0" />
                {{ $evidence->user->name }}
            </span>
            <span>·</span>
            @if ($assignment)
                <span>{{ $assignment->programUnit->name }}</span>
                <span>·</span>
            @endif
            <span>{{ $deliverable->academicPeriod->name }}</span>
            <span>·</span>
            @if ($isCrossCutting)
                <span>Transversal: {{ $deliverable->crossCuttingCommitment->name }}</span>
            @else
                <span>{{ $deliverable->activity->component->name }}</span>
                <span>·</span>
                <span>{{ $deliverable->activity->name }}</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            @if ($deliverable->is_mandatory)
                <span class="badge bg-status-warning-subtle text-sm text-status-warning">
                    Obligatoria{{ $deliverable->weight_percentage !== null ? ' ('.$deliverable->weight_percentage.'%)' : '' }}
                </span>
            @endif
            <span class="inline-flex items-center gap-1">
                <x-icon name="calendar" class="h-4 w-4 shrink-0" />
                @if ($sameDueAndClose)
                    Límite y cierre: {{ $deliverable->due_at->toReadable() }}
                @else
                    Límite: {{ $deliverable->due_at->toReadable() }}
                    @if ($deliverable->closes_at)
                        · Cierre: {{ $deliverable->closes_at->toReadable() }}
                    @endif
                @endif
            </span>
            <span class="inline-flex items-center gap-1">
                <x-icon name="send" class="h-4 w-4 shrink-0" />
                Enviado: {{ $version->submitted_at?->toReadable() ?? '—' }}
                @if ($lateDays !== null)
                    <span class="font-medium text-status-warning">({{ $lateDays }} {{ $lateDays === 1 ? 'día' : 'días' }} tarde)</span>
                @endif
            </span>
            @if ($reviewContext)
                <span class="badge bg-brand-primary-subtle text-sm text-brand-primary-dark">{{ $reviewContext }}</span>
            @endif
        </div>
    </div>

    <x-flash-message />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Columna principal: Entrega para revisar, Tu decisión, Historial. --}}
        <div class="contents lg:flex lg:flex-col lg:col-span-2 lg:gap-6">
            <div class="order-2 lg:order-none">
                <div class="card space-y-4 p-6">
                    <h2 class="form-section-title">Entrega para revisar</h2>

                    @if ($version->files->isNotEmpty())
                        <ul class="space-y-2">
                            @foreach ($version->files as $file)
                                <x-evidence-file-row :file="$file" :available="$file->existsOnDisk()" />
                            @endforeach
                        </ul>
                    @endif

                    @if ($version->submission_comment)
                        <p class="text-base text-text-secondary">
                            <span class="font-medium text-text-primary">Comentario del docente:</span> {{ $version->submission_comment }}
                        </p>
                    @endif

                    @if ($version->files->isEmpty() && ! $version->submission_comment)
                        <p class="text-sm text-text-secondary">Esta versión no tiene archivos ni comentario — ver el resto del contenido en el historial.</p>
                    @endif
                </div>
            </div>

            <div class="order-4 lg:order-none">
                @if ($evidence->status->value === 'submitted')
                    <div class="card space-y-4 p-6">
                        <h2 class="form-section-title">Tu decisión</h2>
                        <div>
                            <label for="observation" class="field-label">
                                Observación <span class="font-normal text-text-secondary">(obligatoria si devuelves)</span>
                            </label>
                            <textarea id="observation" wire:model="observation" rows="3" class="field-input" aria-invalid="{{ $errors->has('observation') ? 'true' : 'false' }}" aria-describedby="observation-error"></textarea>
                            <x-field-error field="observation" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="btn-success"
                                @click="$dispatch('confirm-modal', {
                                    title: 'Aprobar evidencia',
                                    body: '¿Aprobar esta evidencia? El docente verá el estado actualizado de inmediato.',
                                    confirmLabel: 'Aprobar',
                                    variant: 'success',
                                    action: () => $wire.approve(),
                                })"
                                wire:loading.attr="disabled"
                                wire:target="approve,returnForAdjustment"
                            >
                                <span wire:loading.remove wire:target="approve" class="inline-flex items-center gap-2">
                                    <x-icon name="check-circle" class="h-4 w-4" />
                                    Aprobar
                                </span>
                                <span wire:loading wire:target="approve">Aprobando...</span>
                            </button>
                            <button
                                type="button"
                                class="btn-warning"
                                @click="$dispatch('confirm-modal', {
                                    title: 'Devolver evidencia',
                                    body: '¿Devolver esta evidencia para ajustes? El docente podrá editarla y volver a enviarla.',
                                    confirmLabel: 'Devolver',
                                    variant: 'warning',
                                    action: () => $wire.returnForAdjustment(),
                                })"
                                wire:loading.attr="disabled"
                                wire:target="approve,returnForAdjustment"
                            >
                                <span wire:loading.remove wire:target="returnForAdjustment" class="inline-flex items-center gap-2">
                                    <x-icon name="alert-triangle" class="h-4 w-4" />
                                    Devolver
                                </span>
                                <span wire:loading wire:target="returnForAdjustment">Devolviendo...</span>
                            </button>
                            <a href="{{ $this->reviewsIndexUrl() }}" class="btn-secondary">
                                <x-icon name="chevron-left" class="h-4 w-4" />
                                Volver
                            </a>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        @can('reopen', $evidence)
                            @if ($evidence->status->value === 'approved')
                                <button
                                    type="button"
                                    class="btn-secondary"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Reabrir evidencia aprobada',
                                        body: '¿Reabrir esta evidencia aprobada? Esta es una acción excepcional y quedará registrada.',
                                        confirmLabel: 'Reabrir',
                                        variant: 'warning',
                                        action: () => $wire.reopen(),
                                    })"
                                    wire:loading.attr="disabled"
                                    wire:target="reopen"
                                >
                                    <span wire:loading.remove wire:target="reopen">Reabrir (permiso especial)</span>
                                    <span wire:loading wire:target="reopen">Reabriendo...</span>
                                </button>
                            @endif
                        @endcan
                        <a href="{{ $this->reviewsIndexUrl() }}" class="btn-secondary">
                            <x-icon name="chevron-left" class="h-4 w-4" />
                            Volver
                        </a>
                    </div>
                @endif
            </div>

            @if ($evidence->versions->contains(fn ($v) => $v->submitted_at !== null))
                <div class="order-6 lg:order-none">
                    <div class="card p-6">
                        <h2 class="form-section-title mb-3">Historial</h2>
                        <x-evidence-version-history :evidence="$evidence" pending-review-label="Pendiente de tu revisión" />
                    </div>
                </div>
            @endif
        </div>

        {{-- Columna lateral: Instrucciones (con Descripción y Criterio), Material de referencia. --}}
        <div class="contents lg:flex lg:flex-col lg:col-span-1 lg:gap-6">
            @if ($deliverable->description || $deliverable->instructions || $deliverable->completion_criteria)
                {{--
                    Ajustes al rediseño visual, punto 2: elimina la tarjeta
                    azul destacada de "Criterio de cumplimiento" — su
                    contenido pasa a esta misma sección, junto a
                    Descripción e Instrucciones, igual que en la vista del
                    docente. Toma el lugar que tenía esa tarjeta en el
                    orden de móvil (antes de "Tu decisión") y, en
                    escritorio, abre por defecto (sigue siendo plegable:
                    el usuario puede cerrarla).
                --}}
                <div class="order-3 lg:order-none">
                    <x-disclosure summary="Instrucciones" default-open-on-desktop>
                        <div class="space-y-3">
                            @if ($deliverable->description)
                                <div>
                                    <h3 class="font-medium text-text-primary">Descripción</h3>
                                    <x-formatted-text :text="$deliverable->description" />
                                </div>
                            @endif
                            @if ($deliverable->instructions)
                                <div>
                                    <h3 class="font-medium text-text-primary">Instrucciones</h3>
                                    <x-formatted-text :text="$deliverable->instructions" />
                                </div>
                            @endif
                            @if ($deliverable->completion_criteria)
                                <div>
                                    <h3 class="font-medium text-text-primary">Criterio de cumplimiento</h3>
                                    <x-formatted-text :text="$deliverable->completion_criteria" />
                                </div>
                            @endif
                        </div>
                    </x-disclosure>
                </div>
            @endif

            @if ($deliverable->reference_url || $referenceFiles->isNotEmpty())
                <div class="order-5 lg:order-none">
                    <x-reference-material-card :deliverable="$deliverable" :referenceFiles="$referenceFiles" />
                </div>
            @endif
        </div>
    </div>
</div>
