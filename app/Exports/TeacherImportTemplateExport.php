<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TeacherImportTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new TeacherImportTemplateDataSheet,
            new TeacherImportInstructionsSheet,
        ];
    }
}
