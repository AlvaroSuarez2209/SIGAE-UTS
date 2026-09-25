<?php

namespace App\Models\Concerns;

/**
 * Orden por defecto de los listados de catálogos: más reciente primero.
 * Antes cada Livewire Index ordenaba su tabla principal a mano con
 * ->orderBy('name') (alfabético) — se cambia a "más reciente primero"
 * porque, en el uso real, lo que Administración/Coordinación acaba de
 * crear o editar es justamente lo que quiere confirmar arriba de la
 * lista, no buscarlo por orden alfabético.
 *
 * ->orderByDesc('id') en vez de ->orderByDesc('created_at'): id es
 * estrictamente monótono y único, así que el orden queda determinista
 * incluso si varias filas comparten el mismo created_at (seeders,
 * factories en un bucle rápido) — created_at, en cambio, dependería del
 * criterio de desempate no garantizado de PostgreSQL entre filas con el
 * mismo timestamp.
 *
 * Solo se usa para el listado principal de cada catálogo — los <select>
 * de un catálogo dentro del formulario de otro (ej. el desplegable de
 * Componentes en el formulario de Actividades) siguen ordenados por
 * nombre a propósito: ahí lo que ayuda es poder ubicar una opción
 * conocida, no ver la más reciente primero.
 */
trait OrdersNewestFirst
{
    public function scopeNewestFirst($query)
    {
        return $query->orderByDesc('id');
    }
}
