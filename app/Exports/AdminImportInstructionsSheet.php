<?php

namespace App\Exports;

use App\Services\AdminImport\AdminImportService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class AdminImportInstructionsSheet implements Export, FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['Instrucciones para la importación masiva de administradores y coordinadores'],
            [''],
            ['No cambies los nombres de las columnas de la hoja "Plantilla".'],
            ['tipo_documento: uno de '.implode(', ', AdminImportService::DOCUMENT_TYPES).'.'],
            ['numero_documento: sin puntos ni espacios.'],
            ['nombre_completo: nombre y apellidos completos.'],
            ['correo_institucional: correo con el que la persona iniciará sesión.'],
            ['codigo_programa: debe coincidir con el nombre exacto de un programa académico ya registrado en Catálogos > Programas académicos.'],
            ['roles: Administrador, Coordinación, o ambos separados por coma (ej. "Administrador, Coordinación"). No se permite ningún otro rol desde esta plantilla — usa "Importar docentes" para Docente, Líder o Auditor.'],
            ['estado: Activo o Inactivo.'],
            [''],
            ['Las personas creadas reciben un correo para establecer su propia contraseña — no se incluye ninguna contraseña en este archivo.'],
            [''],
            ['IMPORTANTE: la fila de ejemplo de la hoja "Plantilla" usa "'.AdminImportTemplateDataSheet::EXAMPLE_PROGRAM_PLACEHOLDER.'" como marcador de posición en codigo_programa a propósito — ningún programa real se llama así. Reemplázalo por el nombre exacto de un programa de tu propio catálogo antes de agregar tus filas. Si subes la plantilla sin editar esa columna, esa fila será rechazada ("no existe en los programas académicos registrados") — es el comportamiento esperado, no un error del sistema.'],
            [''],
            ['Máximo '.AdminImportService::MAX_ROWS.' filas por archivo.'],
        ];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}
