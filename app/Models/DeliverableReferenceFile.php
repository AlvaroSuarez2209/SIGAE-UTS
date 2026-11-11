<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Archivo de referencia (plantilla/instructivo/material adicional)
 * adjunto a un entregable o a una plantilla de entregable — RF-038.
 * Mismo patrón que `EvidenceFile`:
 * disco privado, `stored_name` aleatorio, nunca una URL pública.
 * `is_active` en vez de borrado físico (ver
 * Deliverable::removeReferenceFile()). Pertenece a exactamente UNO de
 * deliverable_id/deliverable_template_id — nunca ambos ni ninguno (ver
 * el CHECK deliverable_reference_files_exactly_one_owner).
 */
class DeliverableReferenceFile extends Model
{
    use HasFactory;

    public const MAX_FILES = 3;

    public const MAX_FILE_SIZE_MB = 10;

    protected $fillable = [
        'deliverable_id',
        'deliverable_template_id',
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

    public function deliverableTemplate(): BelongsTo
    {
        return $this->belongsTo(DeliverableTemplate::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Ver el docblock equivalente en EvidenceFile::disk() — mismo disco
     * configurable, nunca "local" fijo.
     */
    public function disk()
    {
        return Storage::disk(config('filesystems.attachments_disk'));
    }

    /**
     * Mismo criterio que EvidenceFile::existsOnDisk() — ver su docblock.
     */
    public function existsOnDisk(): bool
    {
        return $this->disk()->exists($this->disk_path);
    }

    /**
     * Punto 5: desde que un entregable creado a partir de una plantilla
     * duplica la fila del archivo de referencia compartiendo disk_path
     * (ver DeliverableForm::save()), dos filas pueden apuntar al mismo
     * archivo físico — entre dos DeliverableReferenceFile (entregable +
     * plantilla, o dos entregables de la misma plantilla) o incluso
     * contra un EvidenceFile. Mismo criterio y mismo nombre de método
     * que EvidenceFile::isDiskPathReferencedElsewhere() (ver su
     * docblock): hay que consultarlo antes de borrar el archivo físico,
     * nunca solo la fila propia.
     */
    public function isDiskPathReferencedElsewhere(): bool
    {
        return self::where('disk_path', $this->disk_path)
            ->where('id', '!=', $this->id)
            ->exists()
            || EvidenceFile::where('disk_path', $this->disk_path)->exists();
    }

    protected function readableSize(): Attribute
    {
        return Attribute::get(fn () => EvidenceFile::formatReadableSize($this->size_bytes));
    }
}
