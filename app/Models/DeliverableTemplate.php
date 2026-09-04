<?php

namespace App\Models;

use App\Enums\PeriodicityType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverableTemplate extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'instructions',
        'completion_criteria',
        'is_mandatory',
        'periodicity_type',
        'allowed_evidence_types',
        'allowed_file_types',
        'max_files',
        'max_file_size_mb',
        'weight_percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'periodicity_type' => PeriodicityType::class,
            'allowed_evidence_types' => 'array',
            'allowed_file_types' => 'array',
        ];
    }
}
