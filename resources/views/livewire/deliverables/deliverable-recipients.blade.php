<div class="mx-auto max-w-2xl">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="page-title">{{ $deliverable->name }}</h1>
            <p class="text-sm text-text-secondary">
                @if ($deliverable->isCrossCutting())
                    {{ $deliverable->crossCuttingCommitment->name }} (transversal)
                @else
                    {{ $deliverable->activity->component->name }} — {{ $deliverable->activity->name }}
                @endif
            </p>
        </div>
        <a href="{{ route('deliverables.index') }}" class="btn-text text-text-secondary">Volver a Entregables</a>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Docente</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($rows as $evidence)
                    <tr class="table-row">
                        <td class="table-cell">{{ $evidence->user->name }}</td>
                        <td class="table-cell">
                            <x-status-badge :status="$evidence->status" />
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('my-deliverables.show', $evidence) }}" class="btn-text">Ver evidencia</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state icon="users" title="No hay destinatarios" description="Este entregable no tiene docentes asignados todavía." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
