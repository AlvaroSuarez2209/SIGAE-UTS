<?php

namespace App\Policies\Concerns;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Punto 2 (borrado real de catálogo): la misma regla de permiso en los
 * 6 tipos de catálogo + periodos académicos — solo Administrador, nunca
 * Coordinación (que sí gestiona creación/edición/desactivación de
 * estos mismos catálogos). Compartido en vez de repetir el mismo
 * método en 7 policies.
 */
trait OnlyAdministratorDeletes
{
    public function delete(User $user, mixed $model): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }
}
