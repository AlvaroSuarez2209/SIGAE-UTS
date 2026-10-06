<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\InstitutionSettings;
use App\Models\User;

class InstitutionSettingsPolicy
{
    public function view(User $user, InstitutionSettings $settings): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    public function update(User $user, InstitutionSettings $settings): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }
}
