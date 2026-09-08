<div class="mx-auto max-w-2xl">
    @php $deliverable = $evidence->deliverable; $version = $evidence->currentVersion; @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="page-title">{{ $deliverable->name }}</h1>
            <p class="text-sm text-text-secondary">{{ $evidence->user->name }}</p>
        </div>
        <x-status-badge :status="$evidence->status" />
    </div>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-success-subtle p-3 text-sm text-status-success">
            <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
            {{ session('status') }}
        </div>
    @endif

    <div class="card mb-6 space-y-2 p-4 text-sm text-text-secondary">
        @if ($deliverable->completion_criteria)
            <p><span class="font-medium text-text-primary">Criterio de cumplimiento:</span> {{ $deliverable->completion_criteria }}</p>
        @endif
        <p><span class="font-medium text-text-primary">Fecha límite:</span> {{ $deliverable->due_at->format('d/m/Y H:i') }}</p>
        <p><span class="font-medium text-text-primary">Enviado:</span> {{ $version->submitted_at?->format('d/m/Y H:i') ?? '—' }}</p>
    </div>

    <div class="card mb-6 space-y-4 p-6">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Evidencia enviada (versión {{ $version->version_number }})</h2>

        @if ($version->description)
            <p class="whitespace-pre-line text-sm text-text-primary">{{ $version->description }}</p>
        @endif

        @if ($version->files->isNotEmpty())
            <ul class="space-y-1">
                @foreach ($version->files as $file)
                    <li>
                        <a href="{{ route('evidence-files.download', $file) }}" class="flex items-center gap-1.5 text-sm text-brand-primary hover:underline">
                            <x-icon name="paperclip" class="h-4 w-4 shrink-0" />
                            {{ $file->original_name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($version->links->isNotEmpty())
            <ul class="space-y-1">
                @foreach ($version->links as $link)
                    <li>
                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-sm text-brand-primary hover:underline">
                            <x-icon name="link" class="h-4 w-4 shrink-0" />
                            {{ $link->label ?: $link->url }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! $version->description && $version->files->isEmpty() && $version->links->isEmpty())
            <p class="text-sm text-text-secondary">Esta versión no tiene contenido adjunto.</p>
        @endif
    </div>

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
                >
                    <x-icon name="check-circle" class="h-4 w-4" />
                    Aprobar
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
                >
                    <x-icon name="alert-triangle" class="h-4 w-4" />
                    Devolver
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
                    >
                        Reabrir (permiso especial)
                    </button>
                </div>
            @endif
        @endcan
        <a href="{{ route('reviews.index') }}" class="btn-text inline-block text-text-secondary">Volver</a>
    @endif

    @if ($evidence->reviews->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">Histórico de revisiones</h2>
            <ul class="space-y-3">
                @foreach ($evidence->reviews as $review)
                    <li class="card p-3 text-sm">
                        <x-status-badge :status="$review->decision" class="mb-1" />
                        <p class="text-text-secondary">por {{ $review->reviewer->name }} — {{ $review->decided_at->format('d/m/Y H:i') }}</p>
                        @foreach ($review->observations as $observation)
                            <p class="mt-1 text-text-secondary">{{ $observation->body }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
