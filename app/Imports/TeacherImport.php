<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Clase mínima: su único rol es que Excel::toCollection() respete la fila 1
 * del archivo como encabezados (tipo_documento, numero_documento, ...) y
 * devuelva cada fila como un array asociativo por esa clave. Toda la
 * validación y el procesamiento viven en
 * App\Services\TeacherImport\TeacherImportService, no aquí.
 * implements Import: interfaz marcador que Maatwebsite Excel v4 exige en
 * Excel::toCollection() (mismo patrón que `Export` en ReportExport).
 */
class TeacherImport implements Import, WithHeadingRow
{
    public function headingRow(): int
    {
        return 1;
    }
}
