<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\EvidenceFile;
use App\Models\User;

class EvidenceFilePolicy
{
    public function view(User $user, EvidenceFile $file): bool
    {
        $evidence = $file->version->evidence;

        return $user->id === $evidence->user_id
            || $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor]);
    }
}
