<?php

namespace App\Exports;

use App\Services\TeacherImport\TeacherImportService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class TeacherImportInstructionsSheet implements Export, FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['Instrucciones para la importación masiva de docentes'],
            [''],
            ['No cambies los nombres de las columnas de la hoja "Plantilla".'],
            ['tipo_documento: uno de '.implode(', ', TeacherImportService::DOCUMENT_TYPES).'.'],
            ['numero_documento: sin puntos ni espacios.'],
            ['nombre_completo: nombre y apellidos completos del docente.'],
            ['correo_institucional: correo con el que el docente iniciará sesión.'],
            ['codigo_programa: debe coincidir con el nombre exacto de un programa académico ya registrado en Catálogos > Programas académicos.'],
            ['roles: Docente, Líder y/o Auditor, separados por coma si es más de uno (ej. "Docente, Líder"). No se permite ningún otro rol desde esta plantilla.'],
            ['estado: Activo o Inactivo.'],
            ['tipo_vinculacion (opcional): Planta, Tiempo completo, Medio tiempo 1M o Medio tiempo 4M (1M = 1 mes, 4M = 4 meses). Puedes dejarla vacía — la persona queda "Sin definir" y se puede completar después desde Usuarios. En una actualización, dejarla vacía NUNCA borra el valor que esa persona ya tenía.'],
            [''],
            ['Los docentes creados reciben un correo para establecer su propia contraseña — no se incluye ninguna contraseña en este archivo.'],
            [''],
            ['IMPORTANTE: la fila de ejemplo de la hoja "Plantilla" usa "'.TeacherImportTemplateDataSheet::EXAMPLE_PROGRAM_PLACEHOLDER.'" como marcador de posición en codigo_programa a propósito — ningún programa real se llama así. Reemplázalo por el nombre exacto de un programa de tu propio catálogo antes de agregar tus filas. Si subes la plantilla sin editar esa columna, esa fila será rechazada ("no existe en los programas académicos registrados") — es el comportamiento esperado, no un error del sistema.'],
            [''],
            ['Máximo '.TeacherImportService::MAX_ROWS.' filas por archivo.'],
        ];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}
