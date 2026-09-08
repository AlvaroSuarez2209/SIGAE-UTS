<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Distribución docente</h1>
        <a href="{{ route('distribution.create') }}" class="btn-primary">
            Nueva asignación
        </a>
    </div>

    <div class="mb-4 flex flex-wrap gap-4">
        <x-search-input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar docente..."
            class="w-full max-w-xs"
        />

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
                    <th class="table-header-cell">Periodo</th>
                    <th class="table-header-cell">Componente / Subcomponente</th>
                    <th class="table-header-cell">Actividad</th>
                    <th class="table-header-cell">Programa</th>
                    <th class="table-header-cell text-right">Horas</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($assignments as $assignment)
                    <tr wire:key="assignment-{{ $assignment->id }}" class="table-row">
                        <td class="table-cell">{{ $assignment->user->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $assignment->academicPeriod->name }}</td>
                        <td class="table-cell text-text-secondary">
                            {{ $assignment->activity->component->name }}
                            @if ($assignment->activity->subcomponent)
                                / {{ $assignment->activity->subcomponent->name }}
                            @endif
                        </td>
                        <td class="table-cell text-text-secondary">{{ $assignment->activity->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $assignment->programUnit->name }}</td>
                        <td class="table-cell text-right text-text-secondary">{{ rtrim(rtrim($assignment->assigned_hours, '0'), '.') }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('distribution.edit', $assignment) }}" class="btn-text">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state icon="document" title="No hay asignaciones registradas" description='Usa el botón "Nueva asignación" para distribuir la carga docente.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $assignments->links() }}
    </div>
</div>
