<div class="max-w-2xl">
    @php $deliverable = $evidence->deliverable; @endphp

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">{{ $deliverable->name }}</h1>
        <span @class([
            'rounded-full px-2 py-1 text-xs font-medium',
            'bg-gray-100 text-gray-600' => $evidence->status->value === 'pending',
            'bg-blue-100 text-blue-700' => $evidence->status->value === 'draft',
            'bg-indigo-100 text-indigo-700' => $evidence->status->value === 'submitted',
            'bg-amber-100 text-amber-700' => $evidence->status->value === 'needs_adjustment',
            'bg-green-100 text-green-700' => $evidence->status->value === 'approved',
            'bg-red-100 text-red-700' => $evidence->status->value === 'expired',
            'bg-slate-200 text-slate-600' => $evidence->status->value === 'exempt',
        ])>
            {{ $evidence->status->label() }}
        </span>
    </div>

    <div class="mb-6 space-y-2 rounded-lg bg-white p-4 text-sm text-gray-600 shadow">
        @if ($deliverable->description)
            <p>{{ $deliverable->description }}</p>
        @endif
        @if ($deliverable->instructions)
            <p><span class="font-medium text-gray-700">Instrucciones:</span> {{ $deliverable->instructions }}</p>
        @endif
        @if ($deliverable->completion_criteria)
            <p><span class="font-medium text-gray-700">Criterio de cumplimiento:</span> {{ $deliverable->completion_criteria }}</p>
        @endif
        <p><span class="font-medium text-gray-700">Fecha límite:</span> {{ $deliverable->due_at->format('d/m/Y H:i') }}</p>
        @if ($deliverable->closes_at)
            <p><span class="font-medium text-gray-700">Cierre:</span> {{ $deliverable->closes_at->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    @if ($submissionError)
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $submissionError }}</div>
    @endif

    @if (! $evidence->status->isEditable())
        <div class="mb-4 rounded-md bg-gray-50 p-4 text-sm text-gray-600">
            Esta evidencia está en estado "{{ $evidence->status->label() }}" y no se puede editar directamente.
        </div>
    @endif

    <div class="space-y-4 rounded-lg bg-white p-6 shadow">
        @if (in_array('text', $allowed))
            <div>
                <label class="block text-sm font-medium text-gray-700">Texto de la evidencia</label>
                <textarea wire:model="description" rows="5" @disabled(! $evidence->status->isEditable()) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        @if (in_array('file', $allowed) || in_array('multiple_files', $allowed))
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Archivos
                    <span class="font-normal text-gray-400">
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
                            <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm">
                                <a href="{{ route('evidence-files.download', $file) }}" class="text-indigo-600 hover:underline">{{ $file->original_name }}</a>
                                @if ($evidence->status->isEditable())
                                    <button type="button" wire:click="removeFile({{ $file->id }})" wire:confirm="¿Eliminar este archivo?" class="text-red-600 hover:underline">Quitar</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($evidence->status->isEditable())
                    <input type="file" wire:model="newFiles" multiple class="mt-2 block w-full text-sm text-gray-600">
                    @error('newFiles') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('newFiles.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>
        @endif

        @if (in_array('link', $allowed))
            <div>
                <label class="block text-sm font-medium text-gray-700">Enlaces</label>

                @if ($evidence->currentVersion && $evidence->currentVersion->links->isNotEmpty())
                    <ul class="mt-2 space-y-1">
                        @foreach ($evidence->currentVersion->links as $link)
                            <li class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ $link->label ?: $link->url }}</a>
                                @if ($evidence->status->isEditable())
                                    <button type="button" wire:click="removeLink({{ $link->id }})" wire:confirm="¿Eliminar este enlace?" class="text-red-600 hover:underline">Quitar</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($evidence->status->isEditable())
                    <div class="mt-2 flex gap-2">
                        <input type="url" wire:model="newLinkUrl" placeholder="https://..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <input type="text" wire:model="newLinkLabel" placeholder="Etiqueta (opcional)" class="block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" wire:click="addLink" class="whitespace-nowrap rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200">Agregar</button>
                    </div>
                    @error('newLinkUrl') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>
        @endif

        @if ($evidence->status->isEditable())
            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="saveDraft" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300">
                    Guardar borrador
                </button>
                <button type="button" wire:click="submit" wire:confirm="¿Confirmas el envío? Una vez enviado no podrás editar esta evidencia directamente." class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Enviar evidencia
                </button>
                <a href="{{ route('my-deliverables.index') }}" class="text-sm text-gray-600 hover:underline">Volver</a>
            </div>
        @else
            <a href="{{ route('my-deliverables.index') }}" class="inline-block text-sm text-gray-600 hover:underline">Volver</a>
        @endif
    </div>

    @if ($evidence->reviews->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Revisiones del líder</h2>
            <ul class="space-y-3">
                @foreach ($evidence->reviews as $review)
                    <li class="rounded-md border border-gray-200 bg-white p-3 text-sm">
                        <p>
                            <span @class(['font-medium', 'text-green-700' => $review->decision->value === 'approved', 'text-amber-700' => $review->decision->value === 'returned'])>
                                {{ $review->decision->label() }}
                            </span>
                            — {{ $review->decided_at->format('d/m/Y H:i') }}
                        </p>
                        @foreach ($review->observations as $observation)
                            <p class="mt-1 text-gray-600">{{ $observation->body }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($evidence->versions->count() > 1)
        <div class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Historial de versiones</h2>
            <ul class="space-y-1 text-sm text-gray-600">
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
