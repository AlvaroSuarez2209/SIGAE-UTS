<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Archivo de referencia (plantilla/instructivo/formato) adjunto a un
 * entregable — RF-038. Mismo patrón que `EvidenceFile`: disco privado,
 * `stored_name` aleatorio, nunca una URL pública. `is_active` en vez de
 * borrado físico (ver DeliverableForm::removeReferenceFile()).
 */
class DeliverableReferenceFile extends Model
{
    use HasFactory;

    public const MAX_FILES = 3;

    public const MAX_FILE_SIZE_MB = 10;

    protected $fillable = [
        'deliverable_id',
        'original_name',
        'stored_name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'is_active',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function disk()
    {
        return Storage::disk('local');
    }

    /**
     * Mismo criterio que EvidenceFile::existsOnDisk() — ver su docblock.
     */
    public function existsOnDisk(): bool
    {
        return $this->disk()->exists($this->disk_path);
    }

    protected function readableSize(): Attribute
    {
        return Attribute::get(fn () => EvidenceFile::formatReadableSize($this->size_bytes));
    }
}
