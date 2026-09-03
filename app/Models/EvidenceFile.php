<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EvidenceFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_version_id',
        'original_name',
        'stored_name',
        'disk_path',
        'mime_type',
        'size_bytes',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(EvidenceVersion::class, 'evidence_version_id');
    }

    public function disk()
    {
        return Storage::disk('local');
    }
}
