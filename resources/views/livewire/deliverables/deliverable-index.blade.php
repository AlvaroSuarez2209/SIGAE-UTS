<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Entregables</h1>
        <a href="{{ route('deliverables.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nuevo entregable
        </a>
    </div>

    <div class="mb-4">
        <select wire:model.live="periodFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Ámbito</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Obligatorio</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Fecha límite</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Destinatarios</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($deliverables as $deliverable)
                    <tr wire:key="deliverable-{{ $deliverable->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $deliverable->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            @if ($deliverable->isCrossCutting())
                                <span class="rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-700">Transversal</span>
                                {{ $deliverable->crossCuttingCommitment->name }}
                            @else
                                {{ $deliverable->activity->component->name }} — {{ $deliverable->activity->name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $deliverable->is_mandatory ? 'Sí' : 'No' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $deliverable->due_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $deliverable->recipients->count() }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('deliverables.edit', $deliverable) }}" class="text-indigo-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No hay entregables registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $deliverables->links() }}
    </div>
</div>
