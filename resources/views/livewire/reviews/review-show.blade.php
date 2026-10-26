<div class="mx-auto max-w-2xl">
    @php $deliverable = $evidence->deliverable; $version = $evidence->currentVersion; @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="page-title">{{ $deliverable->name }}</h1>
            <p class="text-sm text-text-secondary">{{ $evidence->user->name }}</p>
        </div>
        <x-status-badge :status="$evidence->status" />
    </div>

    <x-flash-message />

    @if ($deliverable->description || $deliverable->instructions || $deliverable->completion_criteria || $referenceFiles->isNotEmpty())
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
            @if ($referenceFiles->isNotEmpty())
                <div>
                    <span class="font-medium text-text-primary">Archivo(s) de referencia:</span>
                    <ul class="mt-1 space-y-1">
                        @foreach ($referenceFiles as $file)
                            <li>
                                <a href="{{ route('deliverable-reference-files.download', $file) }}" class="text-brand-primary hover:underline">
                                    {{ $file->original_name }}
                                </a>
                                <span class="text-text-secondary">({{ $file->readable_size }})</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <div class="card mb-6 space-y-2 p-4 text-base text-text-secondary">
        <p><span class="font-medium text-text-primary">Periodo:</span> {{ $deliverable->academicPeriod->name }}</p>
        <p>
            <span class="font-medium text-text-primary">Actividad:</span>
            @if ($deliverable->isCrossCutting())
                Transversal: {{ $deliverable->crossCuttingCommitment->name }}
            @else
                {{ $deliverable->activity->name }}
            @endif
        </p>
        @unless ($deliverable->isCrossCutting())
            <p><span class="font-medium text-text-primary">Componente:</span> {{ $deliverable->activity->component->name }}</p>
        @endunless
        <p><span class="font-medium text-text-primary">Peso porcentual:</span> {{ $deliverable->weight_percentage !== null ? $deliverable->weight_percentage.'%' : '—' }}</p>
        <p><span class="font-medium text-text-primary">Obligatorio:</span> {{ $deliverable->is_mandatory ? 'Sí' : 'No' }}</p>
        <p><span class="font-medium text-text-primary">Fecha límite:</span> {{ $deliverable->due_at->toReadable() }}</p>
        <p><span class="font-medium text-text-primary">Enviado:</span> {{ $version->submitted_at?->toReadable() ?? '—' }}</p>
        @php $lateDays = $deliverable->lateSubmissionDays($version->submitted_at); @endphp
        @if ($lateDays !== null)
            <p class="text-sm font-medium text-status-warning">
                Enviado {{ $lateDays }} {{ $lateDays === 1 ? 'día' : 'días' }} después de la fecha límite.
            </p>
        @endif
    </div>

    <div class="card mb-6 space-y-4 p-6">
        <h2 class="form-section-title">Evidencia enviada (versión {{ $version->version_number }})</h2>

        @if ($version->description)
            <p class="whitespace-pre-line text-base text-text-primary">{{ $version->description }}</p>
        @endif

        @if ($version->files->isNotEmpty())
            <ul class="space-y-2">
                @foreach ($version->files as $file)
                    <x-evidence-file-row :file="$file" />
                @endforeach
            </ul>
        @endif

        @if ($version->links->isNotEmpty())
            <ul class="space-y-2">
                @foreach ($version->links as $link)
                    <x-evidence-link-row :link="$link" />
                @endforeach
            </ul>
        @endif

        @if (! $version->description && $version->files->isEmpty() && $version->links->isEmpty())
            <p class="text-sm text-text-secondary">Esta versión no tiene contenido adjunto.</p>
        @endif
    </div>

    @if ($evidence->versions->count() > 1)
        <div class="card mb-6 p-6">
            <h2 class="form-section-title mb-2">Historial de versiones</h2>
            <ul class="space-y-2 text-base text-text-secondary">
                @foreach ($evidence->versions as $historyVersion)
                    <li>
                        <p>
                            Versión {{ $historyVersion->version_number }} —
                            {{ $historyVersion->submitted_at ? 'enviada el '.$historyVersion->submitted_at->toReadable() : 'en edición' }}
                            @if ($historyVersion->id === $evidence->current_version_id)
                                (actual)
                            @endif
                        </p>

                        @if ($historyVersion->files->isEmpty())
                            <p class="mt-1 pl-4 text-sm text-text-secondary">Sin archivos adjuntos.</p>
                        @else
                            <ul class="mt-1 space-y-1 pl-4">
                                @foreach ($historyVersion->files as $file)
                                    <li class="flex items-center gap-2">
                                        <x-icon name="paperclip" class="h-4 w-4 shrink-0 text-text-secondary" />
                                        <span class="truncate">{{ $file->original_name }}</span>
                                        @if ($file->existsOnDisk())
                                            <a href="{{ route('evidence-files.download', $file) }}" class="btn-text shrink-0">Descargar</a>
                                        @else
                                            <span class="shrink-0 text-sm font-medium text-status-error">Archivo no disponible</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($evidence->status->value === 'submitted')
        <div class="card space-y-4 p-6">
            <div>
                <label class="field-label">
                    Observación <span class="font-normal text-text-secondary">(obligatoria si devuelves)</span>
                </label>
                <textarea wire:model="observation" rows="3" class="field-input"></textarea>
                @error('observation') <p class="field-error">{{ $message }}</p> @enderror
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
                <a href="{{ route('reviews.index') }}" class="btn-text text-text-secondary">Volver</a>
            </div>
        </div>
    @else
        @can('reopen', $evidence)
            @if ($evidence->status->value === 'approved')
                <div class="mb-4">
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
                </div>
            @endif
        @endcan
        <a href="{{ route('reviews.index') }}" class="btn-text inline-block text-text-secondary">Volver</a>
    @endif

    @if ($evidence->reviews->isNotEmpty())
        <div class="mt-6">
            <h2 class="form-section-title mb-2">Histórico de revisiones</h2>
            <ul class="space-y-3">
                @foreach ($evidence->reviews as $review)
                    <li class="card p-3 text-base">
                        <x-status-badge :status="$review->decision" class="mb-1" />
                        <p class="text-text-secondary">
                            Versión {{ $review->version->version_number }} · por {{ $review->reviewer->name }} — {{ $review->decided_at->toReadable() }}
                        </p>
                        @foreach ($review->observations as $observation)
                            <p class="mt-1 text-text-secondary">{{ $observation->body }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
