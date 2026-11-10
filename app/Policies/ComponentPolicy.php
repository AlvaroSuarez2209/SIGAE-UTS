<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class ComponentPolicy
{
    use OnlyAdministratorDeletes;
}
