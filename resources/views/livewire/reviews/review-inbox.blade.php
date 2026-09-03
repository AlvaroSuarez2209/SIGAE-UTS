<div>
    <h1 class="mb-6 text-lg font-semibold text-gray-800">Bandeja de revisión</h1>

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
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Docente</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Entregable</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Ámbito</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Enviado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($pending as $evidence)
                    <tr wire:key="pending-{{ $evidence->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $evidence->user->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $evidence->deliverable->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            @if ($evidence->deliverable->isCrossCutting())
                                <span class="rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-700">Transversal</span>
                            @else
                                {{ $evidence->deliverable->activity->component->name }} — {{ $evidence->deliverable->activity->name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $evidence->currentVersion->submitted_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('reviews.show', $evidence) }}" class="text-indigo-600 hover:underline">Revisar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No hay evidencias pendientes de revisión.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
