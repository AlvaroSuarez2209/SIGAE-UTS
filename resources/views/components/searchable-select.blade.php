{{--
    Selector con búsqueda para listas que pueden crecer mucho en
    producción (p. ej. el docente en los informes, con todos los
    docentes activos del sistema) — un <select> nativo se vuelve
    incómodo de recorrer a partir de unas pocas decenas de opciones.

    Filtra en el navegador sobre las opciones ya cargadas (no hace ida y
    vuelta al servidor): sigue siendo instantáneo incluso con varios
    cientos de registros, y evita construir una búsqueda paginada del
    lado del servidor para un simple selector de un solo valor.

    Uso:
        <x-searchable-select
            wire-model="teacherFilter"
            :options="$teachers->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->all()"
            :selected="$teacherFilter"
            placeholder="Busca por nombre..."
            empty-label="Selecciona un docente"
        />

    `wire-model` es el nombre de la propiedad Livewire a enlazar (se
    interpola dentro de $wire.entangle(...), igual que el patrón ya
    usado en <x-auth-alert> con $wire.{{ $wireProperty }}) — no un
    wire:model real, porque no hay un <input>/<select> nativo detrás:
    el valor vive solo en Alpine, sincronizado en ambos sentidos.
--}}
@props(['options', 'wireModel', 'selected' => null, 'placeholder' => 'Buscar...', 'emptyLabel' => null])

@php
    $selectedOption = collect($options)->first(fn ($option) => (string) $option['value'] === (string) $selected);
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="{
        selected: $wire.entangle('{{ $wireModel }}').live,
        options: @js(array_values($options)),
        query: @js($selectedOption['label'] ?? ''),
        open: false,
        highlighted: 0,
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (! q || (this.selected !== null && this.selected !== '' && q === this.label(this.selected).toLowerCase())) {
                return this.options;
            }
            return this.options.filter((option) => option.label.toLowerCase().includes(q));
        },
        label(value) {
            const found = this.options.find((option) => String(option.value) === String(value));
            return found ? found.label : '';
        },
        select(option) {
            this.selected = option.value;
            this.query = option.label;
            this.open = false;
        },
        clear() {
            this.selected = '';
            this.query = '';
            this.open = false;
        },
    }"
    x-init="$watch('selected', (value) => { query = label(value); })"
    @click.outside="open = false"
>
    <div class="relative">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
        <input
            type="text"
            x-model="query"
            @focus="open = true; highlighted = 0"
            @click="open = true"
            @keydown.escape="open = false"
            @keydown.down.prevent="open = true; highlighted = Math.min(highlighted + 1, filtered.length - 1)"
            @keydown.up.prevent="highlighted = Math.max(highlighted - 1, 0)"
            @keydown.enter.prevent="if (filtered[highlighted]) select(filtered[highlighted])"
            placeholder="{{ $placeholder }}"
            role="combobox"
            aria-expanded="open"
            aria-autocomplete="list"
            autocomplete="off"
            class="field-input mt-0 pl-9 pr-8"
        >
        <button
            type="button"
            x-show="selected !== null && selected !== ''"
            x-cloak
            @click="clear()"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-sm text-text-secondary hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary"
            aria-label="Limpiar selección"
        >
            <x-icon name="x" class="h-4 w-4" />
        </button>
    </div>

    <ul
        x-show="open"
        x-cloak
        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-border-subtle bg-surface py-1 text-sm shadow-lg"
        role="listbox"
    >
        @if ($emptyLabel)
            <li @click="clear()" class="cursor-pointer px-3 py-2 text-text-secondary hover:bg-surface-muted">{{ $emptyLabel }}</li>
        @endif
        <template x-for="(option, index) in filtered" :key="option.value">
            <li
                @click="select(option)"
                @mouseenter="highlighted = index"
                :class="{ 'bg-surface-muted': highlighted === index }"
                class="cursor-pointer px-3 py-2 text-text-primary"
                x-text="option.label"
                role="option"
            ></li>
        </template>
        <li x-show="filtered.length === 0" x-cloak class="px-3 py-2 text-text-secondary">Sin resultados.</li>
    </ul>
</div>
