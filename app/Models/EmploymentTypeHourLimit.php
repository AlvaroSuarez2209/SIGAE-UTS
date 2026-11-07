<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmploymentTypeHourLimit extends Model
{
    use Auditable;

    protected $fillable = [
        'employment_type',
        'weekly_hour_limit',
        'duration_months',
    ];

    protected function casts(): array
    {
        return [
            'weekly_hour_limit' => 'integer',
            'duration_months' => 'integer',
        ];
    }
}
