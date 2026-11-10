<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class ProgramUnitPolicy
{
    use OnlyAdministratorDeletes;
}
