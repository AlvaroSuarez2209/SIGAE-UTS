<?php

namespace App\Policies;

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
}
