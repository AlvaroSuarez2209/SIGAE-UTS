<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvidenceVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_id',
        'version_number',
        'description',
        'submitted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(EvidenceLink::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest('decided_at');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
