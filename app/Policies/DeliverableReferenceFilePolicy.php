<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\DeliverableReferenceFile;
use App\Models\User;

class DeliverableReferenceFilePolicy
{
    /**
     * Un archivo desactivado ("quitado", ver Deliverable::removeReferenceFile())
     * nunca se puede descargar, sin importar el rol — sigue existiendo en
     * disco y en la base solo por trazabilidad, no para seguir sirviéndolo.
     *
     * Punto 5: un archivo de PLANTILLA (deliverable_id nulo) nunca pasa
     * por Deliverable::isReferenceFileViewableBy() — solo quien
     * administra plantillas hoy (Administrador/Coordinación, mismo
     * middleware de las rutas deliverable-templates.*) puede verlo; un
     * docente nunca ve el archivo de una plantilla directamente, solo
     * el que se copia a su propio entregable.
     */
    public function view(User $user, DeliverableReferenceFile $file): bool
    {
        if (! $file->is_active) {
            return false;
        }

        if ($file->deliverable_id) {
            return $file->deliverable->isReferenceFileViewableBy($user);
        }

        return $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]);
    }
}
