<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AdminImportTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new AdminImportTemplateDataSheet,
            new AdminImportInstructionsSheet,
        ];
    }
}
