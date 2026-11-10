{{--
    Ajuste de alineación: envuelve el botón (o grupo de botones, en
    Periodos) que cambia de ancho según el estado de la fila
    ("Activar"/"Desactivar", o "Archivar"+"Reabrir"/"Cerrar"/nada en
    Periodos) con un ancho mínimo fijo — igual en todas las filas,
    activas o inactivas — para que el botón "Eliminar" (y "Editar", que
    va antes) nunca se desplace de posición horizontal entre filas. El
    valor por defecto alcanza para "Desactivar", la palabra más larga
    de los 6 catálogos; Periodos pasa min-width más ancho porque puede
    mostrar dos botones a la vez ("Archivar" + "Reabrir").
--}}
@props(['minWidth' => '7rem'])

<span class="inline-flex items-center gap-2" style="min-width: {{ $minWidth }}">
    {{ $slot }}
</span>
