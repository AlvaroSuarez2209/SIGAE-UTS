<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class DeliverableTemplatePolicy
{
    use OnlyAdministratorDeletes;
}
