{{--
    Botón "Eliminar" compartido por los 7 listados de catálogo (los 6
    tipos de app/Services/Catalog/CatalogDeletionService + Periodos
    académicos). Siempre habilitado — nunca `disabled` — porque la
    comprobación de dependencias ya no se hace al pintar la lista, se
    hace al abrir el modal (ver openDeleteConfirmation() en cada
    Index). El clic siempre abre <x-catalog-delete-modal>, nunca borra
    directo.

    Guarda el botón que lo disparó en window.__sigaeDeleteTrigger antes
    de que Livewire abra el modal, para que <x-catalog-delete-modal>
    pueda devolver el foco ahí al cerrarse (Escape o "Cancelar"/"Cerrar").
--}}
<button
    type="button"
    x-on:click="window.__sigaeDeleteTrigger = $el"
    {{ $attributes->merge(['class' => 'btn-delete']) }}
>{{ $slot }}</button>
