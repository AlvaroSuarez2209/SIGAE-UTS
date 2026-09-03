<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Evidence;
use App\Models\User;

class EvidencePolicy
{
    public function view(User $user, Evidence $evidence): bool
    {
        return $user->id === $evidence->user_id
            || $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor]);
    }

    public function update(User $user, Evidence $evidence): bool
    {
        return $user->id === $evidence->user_id;
    }
}
