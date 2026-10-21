<?php

namespace App\Policies;

use App\Models\DeliverableReferenceFile;
use App\Models\User;

class DeliverableReferenceFilePolicy
{
    /**
     * Un archivo desactivado ("quitado", ver Deliverable::removeReferenceFile())
     * nunca se puede descargar, sin importar el rol — sigue existiendo en
     * disco y en la base solo por trazabilidad, no para seguir sirviéndolo.
     */
    public function view(User $user, DeliverableReferenceFile $file): bool
    {
        return $file->is_active && $file->deliverable->isReferenceFileViewableBy($user);
    }
}
