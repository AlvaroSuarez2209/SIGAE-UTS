<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class ActivityPolicy
{
    use OnlyAdministratorDeletes;
}
