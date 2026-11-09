<?php

namespace App\Models\Concerns;

/**
 * Revisión de la directora, punto 1: los registros inactivos no se
 * ofrecen por defecto en ningún selector, lista, filtro ni reporte —
 * este scope centraliza ese filtro en vez de repetir
 * where('is_active', true) suelto en cada consulta. Las pantallas de
 * administración del propio catálogo (que necesitan ver también los
 * inactivos para reactivarlos) simplemente omiten el scope cuando su
 * interruptor "Mostrar inactivos" está encendido.
 */
trait HasActiveScope
{
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
