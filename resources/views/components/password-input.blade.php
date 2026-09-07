{{--
    Campo de contraseña con botón de mostrar/ocultar propio.

    El ícono es único y persistente: alterna solo por clic, nunca por
    focus/blur del input (ver commits d8005ea y 8fc9f06 para la historia de
    por qué esto tiene que vivir en un solo componente compartido).

    Cualquier atributo no declarado aquí como @props (id, wire:model[.mod],
    class, placeholder, autocomplete, aria-*, autofocus, etc.) se reenvía
    tal cual al <input> real vía $attributes.
--}}
<div class="relative" x-data="{ show: false }">
    <input
        type="password"
        :type="show ? 'text' : 'password'"
        {{ $attributes->merge(['class' => 'field-input pr-10']) }}
    >
    <button
        type="button"
        @click="show = !show"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-text-secondary hover:text-text-primary focus:outline-none focus-visible:text-brand-primary"
        :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        tabindex="-1"
    >
        <x-icon x-show="!show" name="eye" class="h-4 w-4" />
        <x-icon x-show="show" name="eye-off" class="h-4 w-4" style="display: none;" />
    </button>
</div>
