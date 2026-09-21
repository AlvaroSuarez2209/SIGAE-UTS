<?php

namespace App\Livewire\Concerns;

/**
 * Umbral único de registros por página para las vistas de listado de
 * "alto volumen" del sistema (Usuarios, Auditoría, Distribución docente,
 * Líderes, Entregables) — ver docs/manual-diseno.md, sección "Paginación".
 * Antes cada componente hardcodeaba su propio número en `->paginate(N)`
 * (10, 15 o 25 según el módulo, sin ningún criterio compartido); ahora
 * todos usan `->paginate(self::PER_PAGE)`.
 *
 * Los catálogos parametrizables pequeños (Componentes, Subcomponentes,
 * Actividades, Programas, Compromisos transversales, Plantillas de
 * entregables, Periodos) NO usan este trait ni paginan en absoluto: no se
 * espera que crezcan más allá de unas pocas decenas de registros incluso
 * en producción real (mismo criterio ya aplicado al decidir no agregarles
 * `<x-search-input>`).
 */
trait HasStandardPagination
{
    protected const PER_PAGE = 25;
}
