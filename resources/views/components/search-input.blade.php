{{--
    Campo de búsqueda con ícono de lupa alineado a la izquierda, para
    comunicar visualmente que el campo filtra una lista sin depender solo
    del texto del placeholder.

    Cualquier atributo no declarado aquí (wire:model[.mod], placeholder,
    class, id, etc.) se reenvía tal cual al <input> real vía $attributes.
--}}
<div class="relative">
    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
    <input
        type="text"
        {{ $attributes->merge(['class' => 'field-input mt-0 pl-9']) }}
    >
</div>
