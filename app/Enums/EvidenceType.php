<?php

namespace App\Enums;

enum EvidenceType: string
{
    case File = 'file';
    case MultipleFiles = 'multiple_files';
    case Text = 'text';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Archivo',
            self::MultipleFiles => 'Múltiples archivos',
            self::Text => 'Texto',
            self::Link => 'Enlace',
        };
    }
}
