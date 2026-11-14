<div class="mx-auto max-w-5xl">
    @php
        $deliverable = $evidence->deliverable;
        $isCrossCutting = $deliverable->isCrossCutting();
        $sameDueAndClose = $deliverable->closes_at && $deliverable->due_at->eq($deliverable->closes_at);
    @endphp

    {{-- Encabezado: fuera de las tarjetas de contenido, con "Volver" discreto. --}}
    <div class="mb-2 flex items-start justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $deliverable->name }}</h1>
            @if ($deliverable->is_mandatory)
                <span class="badge bg-status-warning-subtle text-status-warning">Obligatoria</span>
            @endif
            <x-status-badge :status="$evidence->status" />
        </div>
        <a href="{{ $this->myDeliverablesIndexUrl() }}" class="btn-text shrink-0 text-sm text-text-secondary">Volver</a>
    </div>
    <p class="mb-6 text-sm text-text-secondary">
        {{ $deliverable->academicPeriod->name }}
        ·
        @if ($isCrossCutting)
            Transversal: {{ $deliverable->crossCuttingCommitment->name }}
        @else
            {{ $deliverable->activity->name }}
        @endif
        ·
        @if ($sameDueAndClose)
            Límite y cierre: {{ $deliverable->due_at->toReadable() }}
        @else
            Límite: {{ $deliverable->due_at->toReadable() }}
            @if ($deliverable->closes_at)
                · Cierre: {{ $deliverable->closes_at->toReadable() }}
            @endif
        @endif
    </p>

    {{-- Franja de estado contextual. --}}
    <div class="mb-6 flex items-center gap-2 rounded-md bg-surface-muted p-4 text-sm text-text-secondary">
        <x-icon :name="$evidence->status->icon()" class="h-4 w-4 shrink-0" />
        {{ $this->statusMessage() }}
    </div>

    <x-flash-message />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Columna principal: Instrucciones, Tu entrega, Historial. --}}
        <div class="contents lg:flex lg:flex-col lg:col-span-2 lg:gap-6">
            <div class="order-4 lg:order-none">
                @if ($deliverable->description || $deliverable->instructions || $deliverable->completion_criteria)
                    <div class="card space-y-4 p-4">
                        @if ($deliverable->description)
                            <div>
                                <h2 class="form-section-title mb-1">Descripción</h2>
                                <x-formatted-text :text="$deliverable->description" />
                            </div>
                        @endif
                        @if ($deliverable->instructions)
                            <div>
                                <h2 class="form-section-title mb-1">Instrucciones</h2>
                                <x-formatted-text :text="$deliverable->instructions" />
                            </div>
                        @endif
                        @if ($deliverable->completion_criteria)
                            <div>
                                <h2 class="form-section-title mb-1">Criterio de cumplimiento</h2>
                                <x-formatted-text :text="$deliverable->completion_criteria" />
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="order-3 lg:order-none">
                <div class="card space-y-4 p-6">
                    <h2 class="form-section-title">Tu entrega</h2>

                    @if ($evidence->status->isEditable())
                        @if (in_array('text', $allowed))
                            <div>
                                <label for="description" class="field-label">Texto de la evidencia</label>
                                <textarea id="description" wire:model="description" rows="5" class="field-input" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-error"></textarea>
                                <x-field-error field="description" />
                            </div>
                        @endif

                        @if (in_array('file', $allowed))
                            @php
                                // draftVersionOrNull(), no currentVersion a secas: tras una
                                // reapertura, currentVersion sigue siendo la versión YA
                                // enviada/revisada hasta la primera edición real — ver
                                // Evidence::draftVersionOrNull() y el mismo criterio en
                                // EvidenceWorkspace::saveDraft()/removeFile().
                                $draftVersion = $evidence->draftVersionOrNull();
                                $currentFiles = $draftVersion?->files ?? collect();
                                $draftFileCount = $currentFiles->count();
                            @endphp
                            <div>
                                <label class="field-label">
                                    Archivos
                                    <span class="field-help inline">{{ $deliverable->fileConstraintsLabel }}</span>
                                </label>

                                @if ($currentFiles->isNotEmpty())
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($currentFiles as $file)
                                            <x-evidence-file-row :file="$file" :removable="true" :available="$file->existsOnDisk()" />
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($draftFileCount < $deliverable->max_files)
                                    <x-file-dropzone wire-model="newFiles" :multiple="true" class="mt-2" />

                                    @if (! empty($newFiles))
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($newFiles as $index => $file)
                                                <li class="flex items-center justify-between gap-3 rounded-md border border-border-subtle px-3 py-2">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <x-icon name="paperclip" class="h-4 w-4 shrink-0 text-text-secondary" />
                                                        <span class="truncate text-base text-text-primary">{{ $file->getClientOriginalName() }}</span>
                                                        <span class="shrink-0 text-sm text-text-secondary">({{ $this->formatFileSize($file->getSize()) }})</span>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-3">
                                                        <button
                                                            type="button"
                                                            wire:click="removeNewFile({{ $index }})"
                                                            class="flex items-center gap-1 text-sm font-medium text-status-error hover:underline"
                                                        >
                                                            <x-icon name="trash" class="h-4 w-4" />
                                                            Quitar
                                                        </button>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @else
                                    <p class="field-help">
                                        Ya alcanzaste el máximo de {{ $deliverable->max_files }} archivo(s) permitido(s). Quita el actual para poder subir uno nuevo.
                                    </p>
                                @endif
                                <x-field-error field="newFiles" />
                                <x-field-error field="newFiles.*" />
                            </div>
                        @endif

                        @if (in_array('link', $allowed))
                            <div>
                                <label class="field-label">Enlaces</label>

                                @if ($evidence->currentVersion && $evidence->currentVersion->links->isNotEmpty())
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($evidence->currentVersion->links as $link)
                                            <x-evidence-link-row :link="$link" :removable="true" />
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="mt-2 flex gap-2">
                                    <label for="newLinkUrl" class="sr-only">URL del enlace</label>
                                    <input type="url" id="newLinkUrl" wire:model="newLinkUrl" placeholder="https://..." class="field-input mt-0" aria-invalid="{{ $errors->has('newLinkUrl') ? 'true' : 'false' }}" aria-describedby="newLinkUrl-error">
                                    <label for="newLinkLabel" class="sr-only">Etiqueta del enlace (opcional)</label>
                                    <input type="text" id="newLinkLabel" wire:model="newLinkLabel" placeholder="Etiqueta (opcional)" class="field-input mt-0 w-48" aria-invalid="{{ $errors->has('newLinkLabel') ? 'true' : 'false' }}" aria-describedby="newLinkLabel-error">
                                    <button
                                        type="button"
                                        wire:click="addLink"
                                        wire:loading.attr="disabled"
                                        wire:target="addLink"
                                        class="btn-secondary whitespace-nowrap"
                                    >
                                        <span wire:loading.remove wire:target="addLink">Agregar</span>
                                        <span wire:loading wire:target="addLink">Agregando...</span>
                                    </button>
                                </div>
                                <x-field-error field="newLinkUrl" />
                                <x-field-error field="newLinkLabel" />
                            </div>
                        @endif

                        <div>
                            <label for="submissionComment" class="field-label">
                                Comentario <span class="font-normal text-text-secondary">(opcional, máx. 2000 caracteres)</span>
                            </label>
                            <textarea id="submissionComment" wire:model="submissionComment" rows="3" maxlength="2000" class="field-input" aria-invalid="{{ $errors->has('submissionComment') ? 'true' : 'false' }}" aria-describedby="submissionComment-error"></textarea>
                            <x-field-error field="submissionComment" />
                        </div>

                        @if ($submissionError)
                            <div class="flex items-center gap-2 rounded-md bg-status-error-subtle p-3 text-sm text-status-error">
                                <x-icon name="alert-circle" class="h-4 w-4 shrink-0" />
                                {{ $submissionError }}
                            </div>
                        @endif

                        <div class="flex items-center gap-3 pt-2">
                            <button
                                type="button"
                                wire:click="saveDraft"
                                wire:loading.attr="disabled"
                                wire:target="saveDraft,submit"
                                class="btn-secondary"
                            >
                                <span wire:loading.remove wire:target="saveDraft">Guardar borrador</span>
                                <span wire:loading wire:target="saveDraft">Guardando...</span>
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
                                wire:loading.attr="disabled"
                                wire:target="saveDraft,submit"
                            >
                                <span wire:loading.remove wire:target="submit">Enviar evidencia</span>
                                <span wire:loading wire:target="submit">Enviando...</span>
                            </button>
                        </div>
                    @else
                        @php
                            $readOnlyVersion = $evidence->currentVersion;
                            $readOnlyFiles = $readOnlyVersion?->files ?? collect();
                            $readOnlyLinks = $readOnlyVersion?->links ?? collect();
                        @endphp

                        @if ($readOnlyVersion?->description)
                            <p class="whitespace-pre-line text-base text-text-primary">{{ $readOnlyVersion->description }}</p>
                        @endif

                        @if ($readOnlyFiles->isNotEmpty())
                            <ul class="space-y-1">
                                @foreach ($readOnlyFiles as $file)
                                    <x-evidence-file-row :file="$file" :available="$file->existsOnDisk()" />
                                @endforeach
                            </ul>
                        @endif

                        @if ($readOnlyLinks->isNotEmpty())
                            <ul class="space-y-1">
                                @foreach ($readOnlyLinks as $link)
                                    <x-evidence-link-row :link="$link" />
                                @endforeach
                            </ul>
                        @endif

                        @if ($readOnlyVersion?->submission_comment)
                            <p class="text-base text-text-secondary">
                                <span class="font-medium text-text-primary">Comentario:</span> {{ $readOnlyVersion->submission_comment }}
                            </p>
                        @endif

                        @if (! $readOnlyVersion?->description && $readOnlyFiles->isEmpty() && $readOnlyLinks->isEmpty() && ! $readOnlyVersion?->submission_comment)
                            <p class="text-sm text-text-secondary">Esta entrega no tiene contenido adjunto.</p>
                        @endif
                    @endif
                </div>
            </div>

            <div class="order-6 lg:order-none">
                @if ($evidence->versions->contains(fn ($v) => $v->submitted_at !== null))
                    <div class="card p-6">
                        <h2 class="form-section-title mb-3">Historial</h2>
                        <x-evidence-version-history :evidence="$evidence" />
                    </div>
                @endif
            </div>
        </div>

        {{-- Columna lateral: Material de referencia, Otras acciones. --}}
        <div class="contents lg:flex lg:flex-col lg:col-span-1 lg:gap-6">
            <div class="order-5 lg:order-none">
                <x-reference-material-card :deliverable="$deliverable" :referenceFiles="$referenceFiles" />
            </div>

            <div class="order-7 lg:order-none">
                @php $isSelfExemption = auth()->id() === $evidence->user_id; @endphp

                @if ($evidence->status->value === 'exempt')
                    {{-- Visible para cualquiera que pueda ver esta página (docente, líder
                         y Coordinación/Administrador) — antes la razón no se le
                         mostraba a nadie, ni siquiera a quien quedó exento. --}}
                    <div class="card space-y-4 p-6">
                        <h2 class="form-section-title">Exención activa</h2>
                        <p class="text-base text-text-secondary">
                            Esta evidencia está marcada como exenta.
                            {{ $isSelfExemption ? 'No puedes editarla ni enviarla' : 'El docente no puede editarla ni enviarla' }}
                            mientras la exención esté activa.
                        </p>
                        <div>
                            <span class="field-label">Motivo</span>
                            <p class="text-base text-text-primary">{{ $evidence->exemption_reason ?? '—' }}</p>
                        </div>

                        @can('removeExemption', $evidence)
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
                                wire:loading.attr="disabled"
                                wire:target="removeExemption"
                            >
                                <span wire:loading.remove wire:target="removeExemption">Quitar exención</span>
                                <span wire:loading wire:target="removeExemption">Quitando...</span>
                            </button>
                        @endcan
                    </div>
                @elseif (auth()->user()->can('markExempt', $evidence))
                    <div class="card space-y-4 p-6">
                        <h2 class="form-section-title">
                            {{ $isSelfExemption ? 'Marcarme como exento' : 'Exención (Administración/Coordinación)' }}
                        </h2>
                        <p class="text-base text-text-secondary">
                            @if ($isSelfExemption)
                                Si tienes otra prioridad que te impide realizar esta entrega (carga académica, un percance, etc.), puedes marcarte como exento: ya no estarás obligado a enviarla y no contará en tu % de avance.
                            @else
                                Eximir a este docente de este entregable: deja de estar obligado a enviarlo y ya no cuenta en su % de avance.
                            @endif
                        </p>
                        <div>
                            <label for="exemptionJustification" class="field-label">
                                Justificación <span class="font-normal text-text-secondary">(obligatoria)</span>
                            </label>
                            <textarea id="exemptionJustification" wire:model="exemptionJustification" rows="3" class="field-input" placeholder="{{ $isSelfExemption ? 'Explica por qué no puedes realizar esta entrega.' : 'Explica por qué el docente no puede realizar esta entrega.' }}" aria-invalid="{{ $errors->has('exemptionJustification') ? 'true' : 'false' }}" aria-describedby="exemptionJustification-error"></textarea>
                            <x-field-error field="exemptionJustification" />
                        </div>
                        <button
                            type="button"
                            class="btn-warning"
                            @click="$dispatch('confirm-modal', {
                                title: '{{ $isSelfExemption ? 'Marcarme como exento' : 'Marcar evidencia como exenta' }}',
                                body: '{{ $isSelfExemption
                                    ? '¿Marcarte como exento de este entregable? Ya no tendrás que enviarlo y dejará de contar en tu porcentaje de avance. Esta acción quedará registrada en la auditoría.'
                                    : '¿Marcar esta evidencia como exenta? El docente ya no tendrá que enviarla y dejará de contar en su porcentaje de avance. Esta acción quedará registrada en la auditoría.' }}',
                                confirmLabel: 'Marcar como exento',
                                variant: 'warning',
                                action: () => $wire.markExempt(),
                            })"
                            wire:loading.attr="disabled"
                            wire:target="markExempt"
                        >
                            <span wire:loading.remove wire:target="markExempt" class="inline-flex items-center gap-2">
                                <x-icon name="shield-check" class="h-4 w-4" />
                                {{ $isSelfExemption ? 'Marcarme como exento' : 'Marcar como exento' }}
                            </span>
                            <span wire:loading wire:target="markExempt">Marcando...</span>
                        </button>
                    </div>
                @elseif ($canManageExemptionByRole)
                    {{-- Tiene el rol para eximir (dueño, Administrador o Coordinación)
                         pero el estado actual no lo permite. --}}
                    <div class="card space-y-2 p-6">
                        <h2 class="form-section-title">Exención</h2>
                        <p class="text-sm text-text-secondary">
                            {{ $isSelfExemption ? 'No puedes marcarte exento en este momento.' : 'No se puede eximir esta evidencia en este momento.' }}
                        </p>
                        <x-disclosure summary="Por qué">
                            @if ($isSelfExemption)
                                No puedes marcarte exento mientras esta evidencia esté Enviada o Aprobada. Esta evidencia está en estado "{{ $evidence->status->label() }}" — si ya la enviaste, espera a que tu líder la revise o la devuelva primero; si ya fue aprobada, no se puede eximir.
                            @else
                                No se puede eximir una evidencia Enviada o Aprobada. Esta evidencia está en estado "{{ $evidence->status->label() }}" — si ya fue enviada, debe devolverse primero; si ya fue aprobada, no se puede eximir.
                            @endif
                        </x-disclosure>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
