<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tope de filas para exportar la bitácora de auditoría
    |--------------------------------------------------------------------------
    |
    | DomPDF no hace streaming: arma todo el árbol de render en memoria antes
    | de generar el PDF, y su costo crece de forma no lineal con el número de
    | filas (medido: ~372 MB/10s a 1.000 filas, ~5 GB/4 min a 5.000) — muy por
    | encima de los 512 MB del contenedor de producción (Render, plan
    | gratuito), que además no tiene ningún `memory_limit`/`max_execution_time`
    | propio (ver Dockerfile/docker/start.sh), así que nada frena a PHP antes
    | de que el contenedor lo mate por falta de memoria. Maatwebsite/Excel
    | escala mucho mejor pero tampoco es gratis (~432 MB medidos a 50.000
    | filas), así que tiene su propio tope, más alto. Si el filtro aplicado
    | (usuario/acción/fecha) supera el tope correspondiente, no se genera el
    | documento — el usuario ve el aviso (mensaje flash de error, igual que
    | cualquier otro error de exportación) solo al intentar la descarga, no
    | antes: el botón nunca se deshabilita. Ver
    | App\Http\Controllers\Audit\AuditLogExportController.
    |
    */

    'pdf_row_limit' => env('AUDIT_PDF_ROW_LIMIT', 300),

    'excel_row_limit' => env('AUDIT_EXCEL_ROW_LIMIT', 20000),

];
