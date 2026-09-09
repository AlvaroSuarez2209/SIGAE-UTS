<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Entregables</h1>
        <a href="{{ route('deliverables.create') }}" class="btn-primary">
            Nuevo entregable
        </a>
    </div>

    <div class="mb-4">
        <select wire:model.live="periodFilter" class="field-input mt-0 w-auto">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Ámbito</th>
                    <th class="table-header-cell">Obligatorio</th>
                    <th class="table-header-cell">Fecha límite</th>
                    <th class="table-header-cell text-right">Destinatarios</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($deliverables as $deliverable)
                    <tr wire:key="deliverable-{{ $deliverable->id }}" class="table-row">
                        <td class="table-cell">{{ $deliverable->name }}</td>
                        <td class="table-cell text-text-secondary">
                            @if ($deliverable->isCrossCutting())
                                <span class="badge bg-category-subtle text-category">
                                    <x-icon name="link" class="h-3.5 w-3.5" />
                                    Transversal
                                </span>
                                {{ $deliverable->crossCuttingCommitment->name }}
                            @else
                                {{ $deliverable->activity->component->name }} — {{ $deliverable->activity->name }}
                            @endif
                        </td>
                        <td class="table-cell text-text-secondary">{{ $deliverable->is_mandatory ? 'Sí' : 'No' }}</td>
                        <td class="table-cell text-text-secondary">{{ $deliverable->due_at->toReadable() }}</td>
                        <td class="table-cell text-right text-text-secondary">{{ $deliverable->recipients->count() }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('deliverables.edit', $deliverable) }}" class="btn-text">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state icon="document" title="No hay entregables registrados" description='Usa el botón "Nuevo entregable" para crear el primero.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $deliverables->links() }}
    </div>
</div>
