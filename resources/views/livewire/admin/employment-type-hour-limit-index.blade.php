<div class="mx-auto max-w-2xl">
    <h1 class="mb-2 page-title">Topes de horas por vinculación</h1>
    <p class="mb-6 text-sm text-text-secondary">
        Tope semanal de horas que puede acumular un docente según su tipo de vinculación, usado al validar su distribución.
        Los valores actuales son <span class="font-medium text-status-warning">provisionales</span> hasta que la directora los confirme.
    </p>

    <x-flash-message />

    <div class="card divide-y divide-border-subtle">
        @foreach ($limits as $limit)
            <div class="flex flex-wrap items-end gap-4 p-4">
                <div class="min-w-[180px]">
                    <span class="field-label">{{ $labels[$limit->employment_type] ?? $limit->employment_type }}</span>
                    <p class="field-help">
                        {{ $limit->duration_months ? "Duración: {$limit->duration_months} ".($limit->duration_months === 1 ? 'mes' : 'meses') : 'Sin duración fija' }}
                    </p>
                </div>
                <div class="flex-1">
                    <label class="field-label" for="limit-{{ $limit->id }}">Tope semanal (horas)</label>
                    <input
                        type="number"
                        min="1"
                        step="1"
                        id="limit-{{ $limit->id }}"
                        wire:model="weeklyHourLimits.{{ $limit->id }}"
                        class="field-input w-32"
                    >
                    @error("weeklyHourLimits.{$limit->id}") <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button
                    type="button"
                    wire:click="save({{ $limit->id }})"
                    wire:loading.attr="disabled"
                    wire:target="save({{ $limit->id }})"
                    class="btn-secondary"
                >
                    <span wire:loading.remove wire:target="save({{ $limit->id }})">Guardar</span>
                    <span wire:loading wire:target="save({{ $limit->id }})">Guardando...</span>
                </button>
            </div>
        @endforeach
    </div>
</div>
