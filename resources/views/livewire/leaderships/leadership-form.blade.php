<div class="max-w-xl">
    <h1 class="mb-6 page-title">
        {{ $leadership ? 'Editar liderazgo' : 'Nuevo liderazgo' }}
    </h1>

    @if ($periodLocked)
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-warning-subtle p-4 text-sm text-status-warning">
            <x-icon name="alert-triangle" class="h-4 w-4 shrink-0" />
            Este periodo está cerrado o archivado. El liderazgo se muestra en modo de solo consulta.
        </div>
    @endif

    <form wire:submit="save" class="card space-y-4 p-6">
        <div>
            <label class="field-label">Líder</label>
            <select wire:model="user_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un líder</option>
                @foreach ($leaders as $leader)
                    <option value="{{ $leader->id }}">{{ $leader->name }}</option>
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
            <label class="field-label">
                Actividad <span class="font-normal text-text-secondary">(opcional — deja en blanco para liderar todo el programa)</span>
            </label>
            <select wire:model="activity_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Todo el programa</option>
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
            <label class="field-label">Vigente desde</label>
            <input type="date" wire:model="starts_at" class="field-input" @if ($periodLocked) disabled @endif>
            @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="field-label">
                Vigente hasta <span class="font-normal text-text-secondary">(opcional, déjalo en blanco si continúa vigente)</span>
            </label>
            <input type="date" wire:model="ends_at" class="field-input" @if ($periodLocked) disabled @endif>
            @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            @unless ($periodLocked)
                <button type="submit" class="btn-primary">
                    Guardar
                </button>
            @endunless
            <a href="{{ route('leaderships.index') }}" class="btn-text text-text-secondary">
                {{ $periodLocked ? 'Volver' : 'Cancelar' }}
            </a>
        </div>
    </form>
</div>
