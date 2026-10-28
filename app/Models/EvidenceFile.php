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
     * El disco de Render es efímero — un redeploy puede dejar la fila sin
     * su archivo físico. Un solo chequeo de existencia en disco local
     * ('local', no 'S3') es una llamada nativa de filesystem, no una
     * petición de red — barato incluso con muchos archivos en pantalla.
     */
    public function existsOnDisk(): bool
    {
        return $this->disk()->exists($this->disk_path);
    }

    /**
     * Revisión de perfil Administrador, punto 6: en producción existen
     * filas de evidence_files que comparten un mismo disk_path por un
     * motivo ajeno a este código (no se pudo determinar el origen — el
     * flujo real de subida siempre genera una ruta nueva y aleatoria por
     * archivo). Antes de borrar el archivo físico al quitar ESTA fila,
     * hay que confirmar que ninguna otra fila —de esta tabla o de
     * deliverable_reference_files, que comparte el mismo disco— sigue
     * apuntando a la misma ruta; si alguna la usa, solo se borra esta
     * fila y el archivo físico se deja intacto.
     */
    public function isDiskPathReferencedElsewhere(): bool
    {
        return self::where('disk_path', $this->disk_path)
            ->where('id', '!=', $this->id)
            ->exists()
            || DeliverableReferenceFile::where('disk_path', $this->disk_path)->exists();
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
