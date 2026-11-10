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

    Ajuste de separación: justify-center (no el flex-start por defecto)
    para que el texto quede centrado en ese ancho reservado — así
    "Activar" (más corto) no deja un hueco desigual junto a "Editar"
    frente a "Desactivar" (más largo), que llenaba más el espacio hacia
    ese lado al quedar pegado a la izquierda del slot.
--}}
@props(['minWidth' => '7rem'])

<span class="inline-flex items-center justify-center gap-2" style="min-width: {{ $minWidth }}">
    {{ $slot }}
</span>
