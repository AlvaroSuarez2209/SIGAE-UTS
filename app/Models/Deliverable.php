<?php

namespace App\Models;

use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends Model
{
    use Auditable, HasFactory;

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

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function isCrossCutting(): bool
    {
        return $this->activity_id === null;
    }

    /**
     * "(máx. 1, 10MB c/u, formatos: .pdf)" — único punto de formato para
     * el resumen de restricciones de archivo, para no repetir a mano la
     * concatenación (y su espaciado) en cada vista que lo muestre.
     */
    protected function fileConstraintsLabel(): Attribute
    {
        return Attribute::get(function () {
            $parts = [
                "máx. {$this->max_files}",
                "{$this->max_file_size_mb}MB c/u",
            ];

            if (! empty($this->allowed_file_types)) {
                $extensions = collect($this->allowed_file_types)->map(fn ($extension) => ".{$extension}")->join(', ');
                $parts[] = "formatos: {$extensions}";
            }

            return '('.implode(', ', $parts).')';
        });
    }

    /**
     * Creates a pending Evidence record for every recipient that doesn't
     * already have one. Never removes evidence for a recipient that was
     * later dropped from the list — evidence history is never deleted.
     */
    public function ensureEvidencesForRecipients(array $userIds): void
    {
        foreach ($userIds as $userId) {
            Evidence::firstOrCreate(
                ['deliverable_id' => $this->id, 'user_id' => $userId],
                ['status' => EvidenceStatus::Pending]
            );
        }
    }
}
