<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class SubcomponentPolicy
{
    use OnlyAdministratorDeletes;
}
