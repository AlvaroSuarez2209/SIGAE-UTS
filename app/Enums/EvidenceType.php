<?php

namespace App\Enums;

enum EvidenceType: string
{
    case File = 'file';
    case Text = 'text';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Archivo',
            self::Text => 'Texto',
            self::Link => 'Enlace',
        };
    }
}
