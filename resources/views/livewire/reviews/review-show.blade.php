<div class="max-w-2xl">
    @php $deliverable = $evidence->deliverable; $version = $evidence->currentVersion; @endphp

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">{{ $deliverable->name }}</h1>
            <p class="text-sm text-gray-500">{{ $evidence->user->name }}</p>
        </div>
        <span @class([
            'rounded-full px-2 py-1 text-xs font-medium',
            'bg-indigo-100 text-indigo-700' => $evidence->status->value === 'submitted',
            'bg-green-100 text-green-700' => $evidence->status->value === 'approved',
            'bg-amber-100 text-amber-700' => $evidence->status->value === 'needs_adjustment',
        ])>
            {{ $evidence->status->label() }}
        </span>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="mb-6 space-y-2 rounded-lg bg-white p-4 text-sm text-gray-600 shadow">
        @if ($deliverable->completion_criteria)
            <p><span class="font-medium text-gray-700">Criterio de cumplimiento:</span> {{ $deliverable->completion_criteria }}</p>
        @endif
        <p><span class="font-medium text-gray-700">Fecha límite:</span> {{ $deliverable->due_at->format('d/m/Y H:i') }}</p>
        <p><span class="font-medium text-gray-700">Enviado:</span> {{ $version->submitted_at?->format('d/m/Y H:i') ?? '—' }}</p>
    </div>

    <div class="mb-6 space-y-4 rounded-lg bg-white p-6 shadow">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Evidencia enviada (versión {{ $version->version_number }})</h2>

        @if ($version->description)
            <p class="whitespace-pre-line text-sm text-gray-700">{{ $version->description }}</p>
        @endif

        @if ($version->files->isNotEmpty())
            <ul class="space-y-1">
                @foreach ($version->files as $file)
                    <li>
                        <a href="{{ route('evidence-files.download', $file) }}" class="text-sm text-indigo-600 hover:underline">{{ $file->original_name }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($version->links->isNotEmpty())
            <ul class="space-y-1">
                @foreach ($version->links as $link)
                    <li>
                        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:underline">{{ $link->label ?: $link->url }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! $version->description && $version->files->isEmpty() && $version->links->isEmpty())
            <p class="text-sm text-gray-400">Esta versión no tiene contenido adjunto.</p>
        @endif
    </div>

    @if ($evidence->status->value === 'submitted')
        <div class="space-y-4 rounded-lg bg-white p-6 shadow">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Observación <span class="font-normal text-gray-400">(obligatoria si devuelves)</span>
                </label>
                <textarea wire:model="observation" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('observation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="approve" wire:confirm="¿Aprobar esta evidencia?" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Aprobar
                </button>
                <button type="button" wire:click="returnForAdjustment" wire:confirm="¿Devolver esta evidencia para ajustes?" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Devolver
                </button>
                <a href="{{ route('reviews.index') }}" class="text-sm text-gray-600 hover:underline">Volver</a>
            </div>
        </div>
    @else
        @can('reopen', $evidence)
            @if ($evidence->status->value === 'approved')
                <div class="mb-4">
                    <button type="button" wire:click="reopen" wire:confirm="¿Reabrir esta evidencia aprobada? Esta es una acción excepcional y quedará registrada." class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300">
                        Reabrir (permiso especial)
                    </button>
                </div>
            @endif
        @endcan
        <a href="{{ route('reviews.index') }}" class="inline-block text-sm text-gray-600 hover:underline">Volver</a>
    @endif

    @if ($evidence->reviews->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Histórico de revisiones</h2>
            <ul class="space-y-3">
                @foreach ($evidence->reviews as $review)
                    <li class="rounded-md border border-gray-200 bg-white p-3 text-sm">
                        <p>
                            <span @class(['font-medium', 'text-green-700' => $review->decision->value === 'approved', 'text-amber-700' => $review->decision->value === 'returned'])>
                                {{ $review->decision->label() }}
                            </span>
                            por {{ $review->reviewer->name }} — {{ $review->decided_at->format('d/m/Y H:i') }}
                        </p>
                        @foreach ($review->observations as $observation)
                            <p class="mt-1 text-gray-600">{{ $observation->body }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
