<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Models\Evidence;
use App\Models\User;

/**
 * Cuánto trabajo pendiente depende de un usuario por su rol de Docente
 * (evidencias propias sin resolver) o de Líder (revisiones bajo su
 * liderazgo vigente) — compartido por UserForm::blocksRoleRemoval() (al
 * quitar un rol existente) y UserIndex::toggleActive() (al desactivar la
 * cuenta por completo), para no duplicar ninguna de las dos consultas.
 */
class PendingWorkChecker
{
    public static function pendingEvidenceCountAsTeacher(User $user): int
    {
        return $user->evidences()
            ->whereNotIn('status', [EvidenceStatus::Approved, EvidenceStatus::Exempt])
            ->count();
    }

    /**
     * Evidence::scopeReviewableBy() con onlyViaLeaderRole: true — mismo
     * criterio de alcance que User::canLeadAssignment() (período +
     * programa + actividad-o-null, dentro de starts_at/ends_at), sin el
     * atajo de Administrador sobre compromisos transversales, porque aquí
     * interesa específicamente lo que depende del rol Líder de $user.
     */
    public static function pendingReviewCountAsLeader(User $user): int
    {
        return Evidence::where('evidences.status', EvidenceStatus::Submitted)
            ->reviewableBy($user, onlyViaLeaderRole: true)
            ->count();
    }
}
