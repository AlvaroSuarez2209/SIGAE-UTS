<?php

namespace App\Policies;

use App\Policies\Concerns\OnlyAdministratorDeletes;

class CrossCuttingCommitmentPolicy
{
    use OnlyAdministratorDeletes;
}
