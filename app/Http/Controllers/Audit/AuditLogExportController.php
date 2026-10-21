<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Concerns\ExportsTabularReport;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogExportBuilder;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Http\Request;

class AuditLogExportController extends Controller
{
    use ExportsTabularReport;

    /**
     * El conteo (con los mismos filtros) se hace ANTES de construir nada:
     * si supera el tope, nunca se llega a AuditLogExportBuilder::build() ni
     * a DomPDF — que no hace streaming y escala de forma no lineal (~372 MB
     * a 1.000 filas, ~5 GB a 5.000, medido) muy por encima de los 512 MB
     * del contenedor de producción. Ver config/audit.php.
     */
    public function downloadPdf(Request $request)
    {
        $filters = $this->filtersFromQuery($request);
        $limit = config('audit.pdf_row_limit');
        $count = AuditLogQuery::filtered($filters)->count();

        if ($count > $limit) {
            $pdfLimit = number_format($limit, 0, ',', '.');
            $excelLimit = number_format(config('audit.excel_row_limit'), 0, ',', '.');

            return redirect()->route('admin.audit-logs.index', $request->query())
                ->with('error', 'Hay '.number_format($count, 0, ',', '.')." registros con este filtro; el máximo para PDF es {$pdfLimit}. Refina por usuario, acción o fecha, o descarga en Excel (hasta {$excelLimit}).");
        }

        // Umbral de 1 (no el valor por defecto de la familia de informes
        // del módulo 9): la bitácora no tiene un filtro siempre presente
        // como "Periodo" ahí, así que cualquier filtro activo, el que sea,
        // se anuncia en el documento — ver el docblock de
        // ExportsTabularReport::pdf().
        return $this->safeExport('admin.audit-logs.index', $request->query(), fn () => $this->pdf(
            AuditLogExportBuilder::build($filters),
            $this->filtersSummary(AuditLogExportBuilder::filtersSummary($filters)),
            1,
        ));
    }

    /**
     * Mismo principio que downloadPdf(), con un tope más alto: Maatwebsite/
     * Excel escala mucho mejor que DomPDF (medido: ~210 MB a 20.000 filas,
     * ~432 MB a 50.000 — ya cerca del límite del contenedor), así que
     * también necesita un techo, aunque menos estricto.
     */
    public function downloadExcel(Request $request)
    {
        $filters = $this->filtersFromQuery($request);
        $limit = config('audit.excel_row_limit');
        $count = AuditLogQuery::filtered($filters)->count();

        if ($count > $limit) {
            $excelLimit = number_format($limit, 0, ',', '.');

            return redirect()->route('admin.audit-logs.index', $request->query())
                ->with('error', 'Hay '.number_format($count, 0, ',', '.')." registros con este filtro; el máximo para Excel es {$excelLimit}. Refina el filtro (usuario, acción o fecha).");
        }

        return $this->safeExport('admin.audit-logs.index', $request->query(), fn () => $this->excel(
            AuditLogExportBuilder::build($filters),
        ));
    }

    /**
     * @return array{user: ?string, action: ?string, from: ?string, to: ?string}
     */
    private function filtersFromQuery(Request $request): array
    {
        return [
            'user' => $request->query('user') ?: null,
            'action' => $request->query('action') ?: null,
            'from' => $request->query('from') ?: null,
            'to' => $request->query('to') ?: null,
        ];
    }
}
