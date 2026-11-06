<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Reporte descargable de las filas rechazadas de una importación de
 * administradores/coordinadores — ver App\Exports\TeacherImportRejectedRowsExport,
 * mismo patrón con el set de columnas más chico de AdminImportService.
 */
class AdminImportRejectedRowsExport implements Export, FromArray, WithHeadings
{
    /**
     * @param  array<int, array<string, mixed>>  $rejectedRows
     */
    public function __construct(private readonly array $rejectedRows) {}

    public function headings(): array
    {
        return [
            'fila',
            'nombre_completo',
            'correo_institucional',
            'roles',
            'estado',
            'motivo_rechazo',
        ];
    }

    public function array(): array
    {
        return array_map(function (array $row) {
            $data = $row['data'];

            return [
                $row['row_number'],
                $data['nombre_completo'] ?? '',
                $data['correo_institucional'] ?? '',
                $data['roles'] ?? '',
                $data['estado'] ?? '',
                implode(' ', $row['errors']),
            ];
        }, $this->rejectedRows);
    }
}
