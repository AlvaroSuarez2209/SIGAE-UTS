<?php

namespace App\Models;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deliverable extends Model
{
    use HasFactory;

    protected $fillable = [
        'deliverable_template_id',
        'academic_period_id',
        'activity_id',
        'cross_cutting_commitment_id',
        'name',
        'description',
        'instructions',
        'completion_criteria',
        'is_mandatory',
        'periodicity_type',
        'opens_at',
        'due_at',
        'closes_at',
        'allowed_evidence_types',
        'allowed_file_types',
        'max_files',
        'max_file_size_mb',
        'weight_percentage',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'periodicity_type' => PeriodicityType::class,
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
            'closes_at' => 'datetime',
            'allowed_evidence_types' => AsEnumCollection::of(EvidenceType::class),
            'allowed_file_types' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DeliverableTemplate::class, 'deliverable_template_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function crossCuttingCommitment(): BelongsTo
    {
        return $this->belongsTo(CrossCuttingCommitment::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'deliverable_recipients');
    }

    public function isCrossCutting(): bool
    {
        return $this->activity_id === null;
    }
}
