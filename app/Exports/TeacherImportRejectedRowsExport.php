<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Reporte descargable de las filas rechazadas de una importación de
 * docentes — generado a partir del resumen en memoria de esa corrida
 * (App\Services\TeacherImport\TeacherImportService::commit()), nunca
 * persistido: son datos efímeros de una sola sesión del asistente.
 */
class TeacherImportRejectedRowsExport implements Export, FromArray, WithHeadings
{
    /**
     * @param  array<int, array<string, mixed>>  $rejectedRows
     */
    public function __construct(private readonly array $rejectedRows) {}

    public function headings(): array
    {
        return [
            'fila',
            'tipo_documento',
            'numero_documento',
            'nombre_completo',
            'correo_institucional',
            'codigo_programa',
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
                $data['tipo_documento'] ?? '',
                $data['numero_documento'] ?? '',
                $data['nombre_completo'] ?? '',
                $data['correo_institucional'] ?? '',
                $data['codigo_programa'] ?? '',
                $data['roles'] ?? '',
                $data['estado'] ?? '',
                implode(' ', $row['errors']),
            ];
        }, $this->rejectedRows);
    }
}
