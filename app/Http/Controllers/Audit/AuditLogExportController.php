<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Concerns\ExportsTabularReport;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogExportBuilder;
use Illuminate\Http\Request;

class AuditLogExportController extends Controller
{
    use ExportsTabularReport;

    public function downloadPdf(Request $request)
    {
        $filters = $this->filtersFromQuery($request);

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

    public function downloadExcel(Request $request)
    {
        $filters = $this->filtersFromQuery($request);

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
