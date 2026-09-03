<div>
    <h1 class="mb-6 text-lg font-semibold text-gray-800">Mis entregables</h1>

    <div class="mb-4 flex gap-4">
        <select wire:model.live="periodFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    @forelse ($grouped as $group => $evidences)
        <div class="mb-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Entregable</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Obligatorio</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Fecha límite</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($evidences as $evidence)
                            <tr wire:key="evidence-{{ $evidence->id }}">
                                <td class="px-4 py-3 text-sm text-gray-800">{{ $evidence->deliverable->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $evidence->deliverable->is_mandatory ? 'Sí' : 'No' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $evidence->deliverable->due_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-sm">
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
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <a href="{{ route('my-deliverables.show', $evidence) }}" class="text-indigo-600 hover:underline">
                                        @if ($evidence->status->isEditable())
                                            Cargar evidencia
                                        @else
                                            Ver
                                        @endif
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">No tienes entregables asignados en este filtro.</p>
    @endforelse
</div>
