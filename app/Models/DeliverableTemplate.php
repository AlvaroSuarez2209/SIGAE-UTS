<?php

namespace App\Models;

use App\Enums\PeriodicityType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

class DeliverableTemplate extends Model
{
    use Auditable, HasActiveScope, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'instructions',
        'completion_criteria',
        'institutional_format_url',
        'is_mandatory',
        'periodicity_type',
        'allowed_evidence_types',
        'allowed_file_types',
        'max_files',
        'max_file_size_mb',
        'weight_percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'periodicity_type' => PeriodicityType::class,
            'allowed_evidence_types' => 'array',
            'allowed_file_types' => 'array',
        ];
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    public function referenceFiles(): HasMany
    {
        return $this->hasMany(DeliverableReferenceFile::class);
    }

    /**
     * Mismo criterio que Deliverable::activeReferenceFiles() — nunca los
     * desactivados.
     */
    public function activeReferenceFiles(): HasMany
    {
        return $this->referenceFiles()->where('is_active', true);
    }

    /**
     * Punto 5: el archivo del formato institucional de la plantilla —
     * en la práctica, a lo único que TemplateForm permite subir (un solo
     * archivo, a diferencia de los hasta 3 de un Deliverable), pero el
     * modelo no impone ese límite aquí; lo hace la validación del
     * formulario.
     */
    public function attachReferenceFile(UploadedFile $file, User $actor): DeliverableReferenceFile
    {
        $storedPath = $file->store('deliverable-templates/'.$this->id, 'local');

        $referenceFile = $this->referenceFiles()->create([
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($storedPath),
            'disk_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);

        AuditLog::record('deliverable_reference_file_attached', $this, [
            'file_name' => $referenceFile->original_name,
            'file_id' => $referenceFile->id,
        ]);

        return $referenceFile;
    }

    /**
     * "Quitar" nunca borra la fila ni el archivo físico — mismo
     * principio que Deliverable::removeReferenceFile().
     */
    public function removeReferenceFile(DeliverableReferenceFile $referenceFile, User $actor): void
    {
        $referenceFile->update(['is_active' => false]);

        AuditLog::record('deliverable_reference_file_removed', $this, [
            'file_name' => $referenceFile->original_name,
            'file_id' => $referenceFile->id,
        ]);
    }
}
