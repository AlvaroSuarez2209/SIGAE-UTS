<?php

namespace App\Policies;

use App\Enums\EvidenceStatus;
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
     * quedar registrado en la bitácora de auditoría (módulo 10). El chequeo
     * de estado vive aquí (no solo en ReviewShow::reopen()) para que
     * Gate::denies('reopen', ...) refleje la regla completa — lo necesita
     * ReviewShow::mount(), que autoriza la página si el usuario puede
     * revisar O reabrir, y antes dejaba pasar a un Administrador que en
     * realidad no podía hacer nada útil ahí (reopen() sobre una evidencia
     * no aprobada no hacía nada, pero la policy por sí sola no lo reflejaba).
     */
    public function reopen(User $user, Evidence $evidence): bool
    {
        return $user->hasRole(RoleName::Administrator) && $evidence->status === EvidenceStatus::Approved;
    }

    /**
     * Revisión del estado Exento (aclaración de la directora): el docente
     * dueño de la evidencia se exime a sí mismo cuando otra prioridad le
     * impide cumplir la entrega — ya no es solo una decisión
     * administrativa institucional. Administrador/Coordinación mantienen
     * la misma capacidad (ej. licencia, reasignación que el docente no
     * gestiona él mismo). El Líder sigue sin tenerla: su ámbito es
     * revisar el contenido de una evidencia dentro de su liderazgo, no
     * decidir si el docente está obligado a presentarla.
     *
     * Bloqueado solo en Enviada (ya hay una revisión en curso; debe
     * resolverse primero, no eximirse por detrás), Aprobada (el resultado
     * ya es definitivo) y Exenta (usar removeExemption). Cualquier otro
     * estado es elegible, incluyendo Borrador y Vencida — Vencida es un
     * valor real de `status` (no calculado: lo pone el comando diario
     * `evidences:mark-overdue`, ver MarkOverdueEvidences), así que se
     * verifica igual que los demás con `in_array`, sin lógica de fecha
     * aparte.
     */
    public function markExempt(User $user, Evidence $evidence): bool
    {
        return ($user->id === $evidence->user_id || $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]))
            && ! in_array($evidence->status, [EvidenceStatus::Submitted, EvidenceStatus::Approved, EvidenceStatus::Exempt], true);
    }

    public function removeExemption(User $user, Evidence $evidence): bool
    {
        return ($user->id === $evidence->user_id || $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]))
            && $evidence->status === EvidenceStatus::Exempt;
    }
}
