<?php

namespace App\Enums;

/**
 * Mismos 4 códigos que ya exige la importación masiva de docentes
 * (TeacherImportService::DOCUMENT_TYPES) — este enum es solo la etiqueta
 * legible de cada código, reutilizada en UserForm, "Mi perfil" y
 * AuditLogPresenter. `users.document_type` sigue siendo una columna de
 * texto plano, nunca cast a este enum: TeacherImportService::resolveAction()
 * compara ese valor crudo tal cual contra el del archivo importado, y un
 * cast a enum rompería esa comparación (un enum nunca es === a un string).
 */
enum DocumentType: string
{
    case CC = 'CC';
    case CE = 'CE';
    case TI = 'TI';
    case PA = 'PA';

    public function label(): string
    {
        return match ($this) {
            self::CC => 'Cédula de ciudadanía',
            self::CE => 'Cédula de extranjería',
            self::TI => 'Tarjeta de identidad',
            self::PA => 'Pasaporte',
        };
    }
}
