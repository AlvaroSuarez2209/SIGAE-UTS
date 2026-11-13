<div class="mx-auto max-w-xl">
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
            <label for="user_id" class="field-label">Líder</label>
            <select id="user_id" wire:model="user_id" class="field-input" @if ($periodLocked) disabled @endif aria-invalid="{{ $errors->has('user_id') ? 'true' : 'false' }}" aria-describedby="user_id-error">
                <option value="">Selecciona un líder</option>
                @foreach ($leaders as $leader)
                    <option value="{{ $leader->id }}">{{ $leader->name }}</option>
                @endforeach
            </select>
            <x-field-error field="user_id" />
        </div>

        <div>
            <label for="academic_period_id" class="field-label">Periodo académico</label>
            <select id="academic_period_id" wire:model="academic_period_id" class="field-input" @if ($periodLocked) disabled @endif aria-invalid="{{ $errors->has('academic_period_id') ? 'true' : 'false' }}" aria-describedby="academic_period_id-error">
                <option value="">Selecciona un periodo</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
            <x-field-error field="academic_period_id" />
        </div>

        <div>
            <label for="program_unit_id" class="field-label">Programa / Unidad académica</label>
            <select id="program_unit_id" wire:model="program_unit_id" class="field-input" @if ($periodLocked) disabled @endif aria-invalid="{{ $errors->has('program_unit_id') ? 'true' : 'false' }}" aria-describedby="program_unit_id-error">
                <option value="">Selecciona un programa</option>
                @foreach ($programUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
            <x-field-error field="program_unit_id" />
        </div>

        <div>
            <label for="component_id" class="field-label">
                Componente <span class="font-normal text-text-secondary">(opcional — solo sirve para filtrar las actividades)</span>
            </label>
            <select id="component_id" wire:model.live="component_id" class="field-input" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona...</option>
                @foreach ($components as $component)
                    <option value="{{ $component->id }}">{{ $component->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="subcomponent_id" class="field-label">
                Subcomponente <span class="font-normal text-text-secondary">(opcional — solo sirve para filtrar las actividades)</span>
            </label>
            <select id="subcomponent_id" wire:model.live="subcomponent_id" class="field-input" @if ($periodLocked || ! $component_id) disabled @endif>
                <option value="">Todos</option>
                @foreach ($availableSubcomponents as $subcomponent)
                    <option value="{{ $subcomponent->id }}">{{ $subcomponent->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="activity_id" class="field-label">
                Actividad <span class="font-normal text-text-secondary">(opcional — deja en blanco para liderar todo el programa)</span>
            </label>
            <select id="activity_id" wire:model="activity_id" class="field-input" @if ($periodLocked || ! $component_id) disabled @endif aria-invalid="{{ $errors->has('activity_id') ? 'true' : 'false' }}" aria-describedby="activity_id-error">
                <option value="">Todo el programa</option>
                @foreach ($activities as $activity)
                    <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                @endforeach
            </select>
            <x-field-error field="activity_id" />
        </div>

        <div>
            <label for="starts_at" class="field-label">Vigente desde</label>
            <input type="date" id="starts_at" wire:model="starts_at" class="field-input" @if ($periodLocked) disabled @endif aria-invalid="{{ $errors->has('starts_at') ? 'true' : 'false' }}" aria-describedby="starts_at-error">
            <x-field-error field="starts_at" />
        </div>

        <div>
            <label for="ends_at" class="field-label">
                Vigente hasta <span class="font-normal text-text-secondary">(opcional, déjalo en blanco si continúa vigente)</span>
            </label>
            <input type="date" id="ends_at" wire:model="ends_at" class="field-input" @if ($periodLocked) disabled @endif aria-invalid="{{ $errors->has('ends_at') ? 'true' : 'false' }}" aria-describedby="ends_at-error">
            <x-field-error field="ends_at" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            @unless ($periodLocked)
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Guardar</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @endunless
            <a href="{{ $this->leadershipsIndexUrl() }}" class="btn-text text-text-secondary">
                {{ $periodLocked ? 'Volver' : 'Cancelar' }}
            </a>
        </div>
    </form>
</div>
