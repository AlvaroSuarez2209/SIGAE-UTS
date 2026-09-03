<div class="max-w-xl">
    <h1 class="mb-6 text-lg font-semibold text-gray-800">
        {{ $assignment ? 'Editar asignación' : 'Nueva asignación' }}
    </h1>

    @if ($periodLocked)
        <div class="mb-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800">
            Este periodo está cerrado o archivado. La asignación se muestra en modo de solo consulta.
        </div>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-6 shadow">
        <div>
            <label class="block text-sm font-medium text-gray-700">Docente</label>
            <select wire:model="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un docente</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
            @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Periodo académico</label>
            <select wire:model="academic_period_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un periodo</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
            @error('academic_period_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Actividad</label>
            <select wire:model="activity_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona una actividad</option>
                @foreach ($activities as $activity)
                    <option value="{{ $activity->id }}">
                        {{ $activity->component->name }}@if ($activity->subcomponent) / {{ $activity->subcomponent->name }}@endif
                        — {{ $activity->name }}
                    </option>
                @endforeach
            </select>
            @error('activity_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Programa / Unidad académica</label>
            <select wire:model="program_unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un programa</option>
                @foreach ($programUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
            @error('program_unit_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Horas asignadas</label>
            <input type="number" step="0.5" wire:model="assigned_hours" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
            <p class="mt-1 text-xs text-gray-400">
                Las horas son solo información de dedicación: no determinan la cantidad de entregables.
            </p>
            @error('assigned_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Notas <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            @unless ($periodLocked)
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar
                </button>
            @endunless
            <a href="{{ route('distribution.index') }}" class="text-sm text-gray-600 hover:underline">
                {{ $periodLocked ? 'Volver' : 'Cancelar' }}
            </a>
        </div>
    </form>
</div>
