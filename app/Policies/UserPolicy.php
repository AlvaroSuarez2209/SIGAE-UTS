<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Revisión de la directora, puntos 7 y 8: Administración ya no es el único
 * rol que gestiona usuarios — Coordinación gestiona su propio subconjunto
 * (Docente/Líder/Auditor), y cada rol queda confinado al suyo (ver
 * managedRoleNames()). El reparto real de roles asignables (qué checkbox
 * se puede marcar) vive en UserForm; esta policy solo decide si el actor
 * puede entrar a ver/crear/editar/activar un usuario dado.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManage($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->managedRoleNames($user) !== [];
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManage($user, $model);
    }

    public function toggleActive(User $user, User $model): bool
    {
        return $user->isNot($model) && $this->canManage($user, $model);
    }

    /**
     * La importación masiva propia del Administrador (Administrador/
     * Coordinación, sin tipo de documento ni programa) es exclusiva de
     * Administración — a diferencia de create()/update(), que ya
     * autorizan a ambos roles de gestión, esta plantilla en particular
     * nunca debe quedar al alcance de Coordinación.
     */
    public function importAdministratorAccounts(User $user): bool
    {
        return $user->hasRole(RoleName::Administrator);
    }

    /**
     * Roles que el actor puede asignar/quitar al crear o editar un
     * usuario — nunca los de su propio "lado". Administración gestiona
     * Administrador/Coordinación; Coordinación gestiona Docente/Líder/
     * Auditor. Ningún otro rol gestiona usuarios en absoluto.
     */
    public function managedRoleNames(User $user): array
    {
        if ($user->hasRole(RoleName::Administrator)) {
            return [RoleName::Administrator->value, RoleName::Coordination->value];
        }

        if ($user->hasRole(RoleName::Coordination)) {
            return [RoleName::Teacher->value, RoleName::Leader->value, RoleName::Auditor->value];
        }

        return [];
    }

    /**
     * Administración puede ver/editar a CUALQUIER usuario (incluyendo
     * Docente/Líder/Auditor, para no romper cuentas existentes ni
     * bloquear la edición de su tipo de vinculación — revisión de la
     * directora, punto 7), pero solo puede ASIGNAR los roles de
     * managedRoleNames() (ver UserForm). Coordinación, en cambio, queda
     * totalmente fuera de cualquier usuario que tenga hoy el rol
     * Administrador o Coordinación — nunca puede verlo, editarlo ni
     * activarlo/desactivarlo, para que nunca pueda escalar sus propios
     * privilegios ni los de otra cuenta de gestión.
     */
    private function canManage(User $actor, User $target): bool
    {
        if ($actor->hasRole(RoleName::Administrator)) {
            return true;
        }

        if ($actor->hasRole(RoleName::Coordination)) {
            $targetRoleNames = $target->exists ? $target->roles->pluck('name')->all() : [];

            return array_intersect($targetRoleNames, [RoleName::Administrator->value, RoleName::Coordination->value]) === [];
        }

        return false;
    }
}
