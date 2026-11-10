<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class AcademicPeriodPolicy
{
    use OnlyAdministratorDeletes;
}
