<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_version_id',
        'reviewer_id',
        'decision',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(EvidenceVersion::class, 'evidence_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }
}
