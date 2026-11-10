{{--
    Ajuste de alineación: contenedor compartido de la celda "Acciones"
    en los 7 listados de catálogo (los 6 tipos de
    app/Services/Catalog/CatalogDeletionService + Periodos académicos).
    flex + whitespace-nowrap evita que los botones se partan en dos
    líneas; gap-4 da el espacio fijo entre ellos (igual al ml-4 que
    usaban antes, pero como gap en vez de margen, para que no se
    acumule con el margen propio de <x-delete-button>). justify-end los
    pega a la derecha, como ya hacía text-right en el <td>.

    Para que "Editar" y "Eliminar" no cambien de posición horizontal
    entre una fila activa y una inactiva (el botón de en medio cambia de
    ancho: "Activar" vs "Desactivar", o ninguno/dos en Periodos), el
    botón o grupo de en medio va envuelto en <x-table-toggle-slot>, que
    reserva un ancho fijo igual en todas las filas.
--}}
<div {{ $attributes->merge(['class' => 'inline-flex items-center justify-end gap-4 whitespace-nowrap']) }}>
    {{ $slot }}
</div>
