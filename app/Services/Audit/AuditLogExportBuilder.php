<?php

namespace App\Services\Audit;

use App\Models\User;

/**
 * Arma la bitácora filtrada en el mismo formato
 * ['title', 'file_identifier', 'sections' => [['title', 'headings', 'rows']]]
 * que App\Services\Reports\ReportBuilder — así la exportación reutiliza tal
 * cual App\Exports\ReportExport (Excel) y
 * resources/views/reports/pdf/report.blade.php (PDF), en vez de duplicar el
 * formato de documento para un quinto caso. Ver
 * App\Http\Controllers\Audit\AuditLogExportController.
 *
 * Sin `summary` (no hay ningún % de avance que resumir aquí) y con
 * `file_identifier` igual a `title`: el título no identifica a ninguna
 * persona (es "Bitácora de auditoría", sin importar qué se haya filtrado),
 * así que no aplica la misma separación que usa
 * ReportBuilder::teacher() para el nombre de archivo.
 */
class AuditLogExportBuilder
{
    /**
     * @param  array{user?: ?string, action?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public static function build(array $filters): array
    {
        $rows = AuditLogQuery::filtered($filters)->get()->map(fn ($log) => [
            $log->created_at->toReadable(),
            $log->user->name ?? 'Sistema',
            AuditLogPresenter::actionLabel($log->action, $log),
            $log->auditableLabel(),
            $log->ip_address ?? '—',
            AuditLogPresenter::describeChanges($log),
        ])->all();

        return [
            'title' => 'Bitácora de auditoría',
            'file_identifier' => 'Bitácora de auditoría',
            'sections' => [
                [
                    'title' => 'Registros',
                    'headings' => ['Fecha', 'Usuario', 'Acción', 'Objeto', 'IP', 'Detalle'],
                    'rows' => $rows,
                ],
            ],
        ];
    }

    /**
     * @param  array{user?: ?string, action?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array<string, ?string>
     */
    public static function filtersSummary(array $filters): array
    {
        return [
            'Usuario' => ($filters['user'] ?? null) ? User::find($filters['user'])?->name : null,
            'Acción' => ($filters['action'] ?? null) ? AuditLogPresenter::actionLabel($filters['action']) : null,
            'Desde' => $filters['from'] ?? null,
            'Hasta' => $filters['to'] ?? null,
        ];
    }
}
