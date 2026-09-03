<?php

namespace App\Enums;

enum EvidenceStatus: string
{
    case Pending = 'pending';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case NeedsAdjustment = 'needs_adjustment';
    case Approved = 'approved';
    case Expired = 'expired';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Draft => 'Borrador',
            self::Submitted => 'Enviado',
            self::NeedsAdjustment => 'Requiere ajustes',
            self::Approved => 'Aprobado',
            self::Expired => 'Vencido',
            self::Exempt => 'Exento',
        };
    }

    /**
     * Estados en los que el docente puede seguir editando la versión actual.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Pending, self::Draft, self::NeedsAdjustment], true);
    }
}
