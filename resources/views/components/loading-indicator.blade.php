{{--
    Indicador breve "Actualizando..." para una barra de filtros completa con
    wire:model.live — mismo patrón ya usado en botones (Identidad
    institucional, Importación de docentes: wire:loading + texto que
    cambia), aplicado aquí a un grupo de filtros en vez de un solo botón.
    Sin wire:target: en el Dashboard y los 4 informes, los filtros son la
    única acción Livewire disparable en la pantalla, así que no hay
    ambigüedad sobre qué está cargando. wire:loading.flex hace que Livewire
    muestre/oculte el elemento automáticamente (display:flex durante el
    request, oculto en reposo) — no hace falta ningún estado de Alpine.
--}}
<div wire:loading.flex {{ $attributes->merge(['class' => 'items-center gap-2 text-sm text-text-secondary']) }}>
    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    Actualizando...
</div>
