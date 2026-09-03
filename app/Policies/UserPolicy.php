<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    public function toggleActive(User $user, User $model): bool
    {
        return $user->hasRole(RoleName::Administrator) && $user->isNot($model);
    }
}
