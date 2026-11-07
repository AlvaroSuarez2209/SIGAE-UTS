<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\EmploymentTypeHourLimit;
use App\Models\User;

class EmploymentTypeHourLimitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]);
    }

    public function update(User $user, EmploymentTypeHourLimit $limit): bool
    {
        return $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]);
    }
}
