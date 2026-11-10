<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Periodos académicos protegidos contra el borrado
    |--------------------------------------------------------------------------
    |
    | Códigos (columna `name` de academic_periods) que el borrado real de
    | catálogo (punto 2) nunca acepta, aunque el periodo no tenga ninguna
    | dependencia — p. ej. el periodo de pruebas de la directora
    | (PRUEBA-COORD-20261026), que debe sobrevivir a cualquier prueba
    | manual. Se revisa tanto en el servidor (CatalogDeletionService) como
    | en la interfaz (botón deshabilitado), nunca solo en uno de los dos.
    |
    */

    'protected_academic_period_codes' => array_filter(array_map(
        'trim',
        explode(',', env('PROTECTED_ACADEMIC_PERIOD_CODES', 'PRUEBA-COORD-20261026'))
    )),

];
