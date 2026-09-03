<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_version_id',
        'url',
        'label',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(EvidenceVersion::class, 'evidence_version_id');
    }
}
