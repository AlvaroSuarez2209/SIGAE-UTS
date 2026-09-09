<div>
    <h1 class="mb-6 page-title">Bandeja de revisión</h1>

    <p class="section-subtitle">
        Evidencias pendientes de aprobación dentro de tu ámbito de revisión.
    </p>

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
                    <th class="table-header-cell">Docente</th>
                    <th class="table-header-cell">Entregable</th>
                    <th class="table-header-cell">Ámbito</th>
                    <th class="table-header-cell">Enviado</th>
                    <th class="table-header-cell text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($pending as $evidence)
                    <tr wire:key="pending-{{ $evidence->id }}" class="table-row">
                        <td class="table-cell">{{ $evidence->user->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $evidence->deliverable->name }}</td>
                        <td class="table-cell text-text-secondary">
                            @if ($evidence->deliverable->isCrossCutting())
                                <span class="badge bg-category-subtle text-category">
                                    <x-icon name="link" class="h-3.5 w-3.5" />
                                    Transversal
                                </span>
                            @else
                                {{ $evidence->deliverable->activity->component->name }} — {{ $evidence->deliverable->activity->name }}
                            @endif
                        </td>
                        <td class="table-cell text-text-secondary">{{ $evidence->currentVersion->submitted_at->toReadable() }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('reviews.show', $evidence) }}" class="btn-text">Revisar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="check-circle" title="No hay evidencias pendientes de revisión" description="Todo lo enviado en tu ámbito ya fue atendido." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
