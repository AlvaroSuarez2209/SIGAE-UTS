<div>
    <h1 class="mb-6 page-title">Mis entregables</h1>

    <div class="mb-4 flex flex-wrap gap-4">
        <select wire:model.live="periodFilter" class="field-input mt-0 w-auto">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="field-input mt-0 w-auto">
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    @forelse ($grouped as $group => $evidences)
        <div class="mb-6">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $group }}</h2>
            <div class="table-shell">
                <table class="min-w-full divide-y divide-border-subtle">
                    <thead>
                        <tr>
                            <th class="table-header-cell">Entregable</th>
                            <th class="table-header-cell">Obligatorio</th>
                            <th class="table-header-cell">Fecha límite</th>
                            <th class="table-header-cell">Estado</th>
                            <th class="table-header-cell text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($evidences as $evidence)
                            <tr wire:key="evidence-{{ $evidence->id }}" class="table-row">
                                <td class="table-cell">{{ $evidence->deliverable->name }}</td>
                                <td class="table-cell text-text-secondary">{{ $evidence->deliverable->is_mandatory ? 'Sí' : 'No' }}</td>
                                <td class="table-cell text-text-secondary">{{ $evidence->deliverable->due_at->toReadable() }}</td>
                                <td class="table-cell">
                                    <x-status-badge :status="$evidence->status" />
                                </td>
                                <td class="table-cell text-right">
                                    <a href="{{ route('my-deliverables.show', $evidence) }}" class="btn-text">
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
        <x-empty-state icon="inbox" title="No tienes entregables en este filtro" description="Ajusta el periodo o el estado seleccionados, o vuelve más adelante." />
    @endforelse
</div>
