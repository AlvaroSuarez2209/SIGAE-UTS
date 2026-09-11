<?php

namespace App\Policies;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\Evidence;
use App\Models\User;

class EvidencePolicy
{
    public function view(User $user, Evidence $evidence): bool
    {
        return $evidence->isViewableBy($user);
    }

    public function update(User $user, Evidence $evidence): bool
    {
        return $user->id === $evidence->user_id;
    }

    public function review(User $user, Evidence $evidence): bool
    {
        return $evidence->isReviewableBy($user);
    }

    /**
     * "Permiso especial" para reabrir una evidencia ya aprobada — exige
     * quedar registrado en la bitácora de auditoría (módulo 10).
     */
    public function reopen(User $user, Evidence $evidence): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    /**
     * Marcar una evidencia como exenta es una decisión administrativa
     * institucional (ej. licencia, reasignación), no una decisión de
     * revisión por pares — se restringe a Administrador/Coordinación,
     * igual que activar/desactivar catálogos y plantillas. Un Líder no la
     * tiene: su ámbito es revisar el contenido de una evidencia dentro de
     * su liderazgo, no decidir si el docente está obligado a presentarla.
     * No se puede eximir una evidencia ya aprobada (el resultado ya es
     * definitivo) ni una que ya está exenta (usar removeExemption).
     */
    public function markExempt(User $user, Evidence $evidence): bool
    {
        return $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination])
            && ! in_array($evidence->status, [EvidenceStatus::Approved, EvidenceStatus::Exempt], true);
    }

    public function removeExemption(User $user, Evidence $evidence): bool
    {
        return $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination])
            && $evidence->status === EvidenceStatus::Exempt;
    }
}
