<?php

namespace App\Models;

use App\Enums\EvidenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidences';

    protected $fillable = [
        'deliverable_id',
        'user_id',
        'status',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvidenceStatus::class,
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(EvidenceVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EvidenceVersion::class)->orderBy('version_number');
    }

    /**
     * Returns the version the docente should keep editing: the existing
     * current version if it hasn't been submitted yet, or a brand new one
     * otherwise. Each adjustment-and-resend cycle produces a new version;
     * the previous one is never modified or lost (see business rules in
     * [[project-sigae-uts-stack]] Módulo 5/6).
     */
    public function startOrGetDraftVersion(User $actor): EvidenceVersion
    {
        if ($this->currentVersion && $this->currentVersion->submitted_at === null) {
            return $this->currentVersion;
        }

        $nextVersionNumber = ($this->versions()->max('version_number') ?? 0) + 1;

        $version = $this->versions()->create([
            'version_number' => $nextVersionNumber,
            'created_by' => $actor->id,
        ]);

        $this->update([
            'current_version_id' => $version->id,
            'status' => EvidenceStatus::Draft,
        ]);

        return $version;
    }

    public function submitCurrentVersion(): void
    {
        $this->currentVersion->update(['submitted_at' => now()]);
        $this->update(['status' => EvidenceStatus::Submitted]);
    }
}
