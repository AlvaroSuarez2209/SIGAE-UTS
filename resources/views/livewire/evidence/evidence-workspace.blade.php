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

    <x-flash-message />

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

        @if (in_array('file', $allowed))
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
                    @error('newLinkUrl') <p class="field-error">{{ $message }}</p> @enderror
                    @error('newLinkLabel') <p class="field-error">{{ $message }}</p> @enderror
                @endif
            </div>
        @endif

        @if ($evidence->status->isEditable())
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
                <a href="{{ route('my-deliverables.index') }}" class="btn-text">Volver</a>
            </div>
        @else
            <a href="{{ route('my-deliverables.index') }}" class="btn-text inline-block">Volver</a>
        @endif
    </div>

    @php $isSelfExemption = auth()->id() === $evidence->user_id; @endphp

    @if ($evidence->status->value === 'exempt')
        {{-- Visible para cualquiera que pueda ver esta página (docente, líder
             y Coordinación/Administrador) — antes la razón no se le
             mostraba a nadie, ni siquiera a quien quedó exento. --}}
        <div class="card mt-6 space-y-4 p-6">
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
        <div class="card mt-6 space-y-4 p-6">
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
                <label class="field-label">
                    Justificación <span class="font-normal text-text-secondary">(obligatoria)</span>
                </label>
                <textarea wire:model="exemptionJustification" rows="3" class="field-input" placeholder="{{ $isSelfExemption ? 'Explica por qué no puedes realizar esta entrega.' : 'Explica por qué el docente no puede realizar esta entrega.' }}"></textarea>
                @error('exemptionJustification') <p class="field-error">{{ $message }}</p> @enderror
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
             pero el estado actual no lo permite — sin este aviso, la
             sección de exención simplemente desaparecería sin explicar
             por qué. --}}
        <div class="mt-6 flex items-center gap-2 rounded-md bg-surface-muted p-4 text-sm text-text-secondary">
            <x-icon name="alert-circle" class="h-4 w-4 shrink-0" />
            @if ($isSelfExemption)
                No puedes marcarte exento mientras esta evidencia esté Enviada o Aprobada. Esta evidencia está en estado "{{ $evidence->status->label() }}" — si ya la enviaste, espera a que tu líder la revise o la devuelva primero; si ya fue aprobada, no se puede eximir.
            @else
                No se puede eximir una evidencia Enviada o Aprobada. Esta evidencia está en estado "{{ $evidence->status->label() }}" — si ya fue enviada, debe devolverse primero; si ya fue aprobada, no se puede eximir.
            @endif
        </div>
    @endif

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
