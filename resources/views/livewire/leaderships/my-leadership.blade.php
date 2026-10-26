<div>
    <h1 class="mb-6 page-title">Mi ámbito</h1>

    <p class="section-subtitle">
        Actividades, componentes y periodos sobre los que tienes autoridad como líder.
    </p>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Periodo</th>
                    <th class="table-header-cell">Componente</th>
                    <th class="table-header-cell">Actividad</th>
                    <th class="table-header-cell">Inicio</th>
                    <th class="table-header-cell">Fin</th>
                    <th class="table-header-cell">Vigente hoy</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($leaderships as $leadership)
                    <tr wire:key="my-leadership-{{ $leadership->id }}" class="table-row">
                        <td class="table-cell text-text-secondary">{{ $leadership->academicPeriod->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $leadership->programUnit->name }}</td>
                        <td class="table-cell text-text-secondary">
                            @if ($leadership->activity)
                                {{ $leadership->activity->name }}
                            @else
                                <span class="text-text-secondary">Todo el programa</span>
                            @endif
                        </td>
                        <td class="table-cell text-text-secondary">{{ $leadership->starts_at->toReadable() }}</td>
                        <td class="table-cell text-text-secondary">{{ $leadership->ends_at?->toReadable() ?? '—' }}</td>
                        <td class="table-cell">
                            @if ($leadership->isActiveOn(today()))
                                <span class="badge bg-status-success-subtle text-status-success">Sí</span>
                            @else
                                <span class="badge bg-status-error-subtle text-status-error">No</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state icon="shield-check" title="No tienes ningún ámbito asignado" description="Coordinación todavía no te ha asignado como líder de ninguna actividad o programa." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
