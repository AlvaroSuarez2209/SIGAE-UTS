<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Distribución docente</h1>
        <a href="{{ route('distribution.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nueva asignación
        </a>
    </div>

    <div class="mb-4 flex gap-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar docente..."
            class="w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >

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
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Periodo</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Componente / Subcomponente</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Actividad</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Programa</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Horas</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($assignments as $assignment)
                    <tr wire:key="assignment-{{ $assignment->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $assignment->user->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $assignment->academicPeriod->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $assignment->activity->component->name }}
                            @if ($assignment->activity->subcomponent)
                                / {{ $assignment->activity->subcomponent->name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $assignment->activity->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $assignment->programUnit->name }}</td>
                        <td class="px-4 py-3 text-right text-sm text-gray-600">{{ rtrim(rtrim($assignment->assigned_hours, '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('distribution.edit', $assignment) }}" class="text-indigo-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No hay asignaciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $assignments->links() }}
    </div>
</div>
