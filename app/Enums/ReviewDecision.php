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

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Returned => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Approved => 'check-circle',
            self::Returned => 'alert-triangle',
        };
    }
}
