<?php

namespace App\Livewire\Concerns;

/**
 * Umbral único de registros por página para las vistas de listado de
 * "alto volumen" del sistema (Usuarios, Auditoría, Distribución docente,
 * Líderes, Entregables, Actividades) — ver docs/manual-diseno.md, sección
 * "Paginación". Antes cada componente hardcodeaba su propio número en
 * `->paginate(N)` (10, 15 o 25 según el módulo, sin ningún criterio
 * compartido); ahora todos usan `->paginate(self::PER_PAGE)`.
 *
 * Los catálogos parametrizables pequeños (Componentes, Subcomponentes,
 * Programas, Compromisos transversales, Plantillas de entregables,
 * Periodos) NO usan este trait ni paginan en absoluto: no se espera que
 * crezcan más allá de unas pocas decenas de registros incluso en
 * producción real. Actividades es la excepción entre los catálogos: en la
 * práctica crece mucho más que los demás (de ahí que ya tuviera su propio
 * `<x-search-input>` antes de sumar paginación), así que si algún otro
 * catálogo empieza a superar sistemáticamente el umbral de 25 en
 * producción real, es la señal de que también debería sumarse aquí.
 */
trait HasStandardPagination
{
    protected const PER_PAGE = 25;
}
