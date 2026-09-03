<?php

namespace App\Policies;

use App\Models\EvidenceFile;
use App\Models\User;

class EvidenceFilePolicy
{
    public function view(User $user, EvidenceFile $file): bool
    {
        return $file->version->evidence->isViewableBy($user);
    }
}
