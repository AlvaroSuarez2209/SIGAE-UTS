<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Approved = 'approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Aprobado',
            self::Returned => 'Devuelto',
        };
    }
}
