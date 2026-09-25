<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Tamaño legible ("245 KB", "1.2 MB") — único punto de formato para no
     * repetir la conversión de bytes en cada vista que liste archivos.
     * Estático porque también lo usa EvidenceWorkspace para los archivos
     * recién adjuntados (TemporaryUploadedFile), que no tienen este
     * accessor por no ser un EvidenceFile persistido todavía.
     */
    public static function formatReadableSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === end($units)) {
                return round($value, 1).' '.$unit;
            }

            $value /= 1024;
        }
    }

    protected function readableSize(): Attribute
    {
        return Attribute::get(fn () => static::formatReadableSize($this->size_bytes));
    }
}
