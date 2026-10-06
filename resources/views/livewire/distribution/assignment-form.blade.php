<div class="mx-auto max-w-xl">
    <h1 class="mb-6 page-title">
        {{ $assignment ? 'Editar asignación' : 'Nueva asignación' }}
    </h1>

    @if ($periodLocked)
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-warning-subtle p-4 text-sm text-status-warning">
            <x-icon name="alert-triangle" class="h-4 w-4 shrink-0" />
            Este periodo está cerrado o archivado. La asignación se muestra en modo de solo consulta.
        </div>
    @endif

    <form wire:submit="save" class="card space-y-4 p-6">
        <div>
            <label class="field-label">Docente</label>
            <select wire:model="user_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un docente</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
            @error('user_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Periodo académico</label>
            <select wire:model="academic_period_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un periodo</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
            @error('academic_period_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Actividad</label>
            <select wire:model="activity_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona una actividad</option>
                @foreach ($activities as $activity)
                    <option value="{{ $activity->id }}">
                        {{ $activity->component->name }}@if ($activity->subcomponent) / {{ $activity->subcomponent->name }}@endif
                        — {{ $activity->name }}
                    </option>
                @endforeach
            </select>
            @error('activity_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Programa / Unidad académica</label>
            <select wire:model="program_unit_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un programa</option>
                @foreach ($programUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
            @error('program_unit_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Horas asignadas</label>
            <input type="number" step="0.5" wire:model="assigned_hours" class="field-input" @if ($periodLocked) disabled @endif>
            <p class="field-help">
                Las horas son solo información de dedicación: no determinan la cantidad de entregables.
            </p>
            @error('assigned_hours') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">Notas <span class="font-normal text-text-secondary">(opcional)</span></label>
            <textarea wire:model="notes" rows="3" class="field-input" @if ($periodLocked) disabled @endif></textarea>
            @error('notes') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            @unless ($periodLocked)
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @endunless
            <a href="{{ route('distribution.index') }}" class="btn-text text-text-secondary">
                {{ $periodLocked ? 'Volver' : 'Cancelar' }}
            </a>
        </div>
    </form>
</div>
