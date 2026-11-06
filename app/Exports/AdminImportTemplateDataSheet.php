<?php

namespace App\Exports;

use App\Services\AdminImport\AdminImportService;
use App\Services\Reports\ReportTheme;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja de datos de la plantilla de importación de administradores/
 * coordinadores (ver App\Exports\TeacherImportTemplateDataSheet, mismo
 * patrón de formato) — revisión de la directora, punto 7. A diferencia de
 * la plantilla de docentes, no lleva tipo_documento ni codigo_programa:
 * ninguno de los dos aplica a Administrador ni Coordinación.
 */
class AdminImportTemplateDataSheet implements Export, FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return AdminImportService::REQUIRED_HEADERS;
    }

    public function array(): array
    {
        return [
            ['Ana María Pérez Gómez', 'ana.perez@uts.edu.co', 'Coordinación', 'Activo'],
        ];
    }

    public function title(): string
    {
        return 'Plantilla';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => ltrim(ReportTheme::BRAND_PRIMARY, '#')],
                    ],
                ]);

                $sheet->freezePane('A2');

                $lastRow = AdminImportService::MAX_ROWS + 1;

                $rolesValidation = $this->listValidation('Administrador,Coordinación');
                $estadoValidation = $this->listValidation('Activo,Inactivo');

                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getCell("C{$row}")->setDataValidation(clone $rolesValidation);
                    $sheet->getCell("D{$row}")->setDataValidation(clone $estadoValidation);
                }
            },
        ];
    }

    private function listValidation(string $commaSeparatedOptions): DataValidation
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Valor no válido');
        $validation->setError('Selecciona un valor de la lista.');
        $validation->setPromptTitle('Selecciona un valor');
        $validation->setPrompt('Elige una opción de la lista desplegable.');
        $validation->setFormula1('"'.$commaSeparatedOptions.'"');

        return $validation;
    }
}
