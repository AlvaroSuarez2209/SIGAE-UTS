<?php

namespace App\Http\Controllers\Concerns;

use App\Exports\ReportExport;
use App\Services\Reports\ReportTheme;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Plumbing de exportación compartida por cualquier controlador que
 * descargue un reporte tabular (el formato
 * ['title', 'file_identifier', 'sections', 'summary'?] que ya arman
 * App\Services\Reports\ReportBuilder y App\Services\Audit\AuditLogExportBuilder)
 * a PDF o Excel — usada por ReportExportController (módulo 9) y
 * App\Http\Controllers\Audit\AuditLogExportController (Prioridad 7), para
 * no repetir el manejo de errores, la numeración de páginas y el nombre de
 * archivo en cada controlador nuevo que exporte un reporte con esta forma.
 */
trait ExportsTabularReport
{
    private function filtersSummary(array $filters): array
    {
        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Protege la generación real (agregación + render de PDF/Excel) de
     * cualquier falla inesperada (ej. un logo corrupto que rompe
     * PhpSpreadsheet\Drawing, un error de agregación) — el usuario nunca
     * debe ver una página 500 cruda por esto. Los findOrFail() de cada
     * controlador quedan FUERA de este wrapper a propósito: un id
     * inexistente en la URL debe seguir siendo un 404 real, no un mensaje
     * genérico de "no se pudo generar", que sería engañoso.
     */
    private function safeExport(string $backRoute, array $backParams, Closure $generate)
    {
        try {
            return $generate();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route($backRoute, $backParams)
                ->with('error', 'No se pudo generar el documento. Intenta de nuevo o contacta al administrador si el problema persiste.');
        }
    }

    /**
     * "Filtros aplicados" solo se muestra cuando hay al menos
     * $minFiltersToShow filtros activos. Los 4 informes del módulo 9
     * siempre tienen "Periodo" activo, así que su umbral (2, el valor por
     * defecto: "más de uno") evita repetirlo cuando es el único filtro. La
     * bitácora de auditoría no tiene ningún filtro siempre presente como
     * ese, así que pasa 1: cualquier filtro activo, el que sea, se anuncia
     * — es un registro institucional y nunca debe sugerir que se exportó
     * todo si en realidad se exportó un subconjunto filtrado.
     */
    private function pdf(array $report, array $filters = [], int $minFiltersToShow = 2)
    {
        $pdf = Pdf::loadView('reports.pdf.report', $report + [
            'filtersSummary' => count($filters) >= $minFiltersToShow ? $filters : null,
        ]);

        // Numeración de páginas vía canvas nativo de DomPDF, no CSS — ver
        // ReportTheme::stampPageNumbers() para el motivo.
        ReportTheme::stampPageNumbers($pdf);

        return $pdf->download($this->fileName($report, 'pdf'));
    }

    private function excel(array $report)
    {
        return Excel::download(new ReportExport($report), $this->fileName($report, 'xlsx'));
    }

    /**
     * El nombre del archivo se arma a partir de `file_identifier`, nunca de
     * `title` — ver el docblock de ReportBuilder para el porqué (evitar que
     * un dato personal como el nombre de un docente quede en el nombre del
     * archivo, aunque sí aparezca con normalidad dentro del documento).
     */
    private function fileName(array $report, string $extension): string
    {
        return Str::slug($report['file_identifier']).'.'.$extension;
    }
}
