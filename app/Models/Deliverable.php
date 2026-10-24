<?php

namespace App\Models;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Enums\RoleName;
use App\Models\Concerns\Auditable;
use App\Notifications\Evidence\EvidenceAssignedNotification;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

class Deliverable extends Model
{
    use Auditable, HasFactory;

    /**
     * Whitelist aplicada cuando este entregable admite evidencia tipo
     * "Archivo"/"Múltiples archivos" pero Administración/Coordinación
     * dejó `allowed_file_types` sin configurar (campo opcional, ver
     * DeliverableForm) — nunca se debe interpretar "sin configurar" como
     * "cualquier extensión es válida". Ver effectiveAllowedFileTypes() y
     * la sección de subida de archivos en docs/manual-tecnico.md.
     */
    public const DEFAULT_ALLOWED_FILE_TYPES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    protected $fillable = [
        'deliverable_template_id',
        'status',
        'academic_period_id',
        'activity_id',
        'cross_cutting_commitment_id',
        'name',
        'description',
        'instructions',
        'completion_criteria',
        'is_mandatory',
        'periodicity_type',
        'opens_at',
        'due_at',
        'closes_at',
        'allowed_evidence_types',
        'allowed_file_types',
        'max_files',
        'max_file_size_mb',
        'weight_percentage',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'status' => DeliverableStatus::class,
            'periodicity_type' => PeriodicityType::class,
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
            'closes_at' => 'datetime',
            'allowed_evidence_types' => AsEnumCollection::of(EvidenceType::class),
            'allowed_file_types' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DeliverableTemplate::class, 'deliverable_template_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function crossCuttingCommitment(): BelongsTo
    {
        return $this->belongsTo(CrossCuttingCommitment::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'deliverable_recipients');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function referenceFiles(): HasMany
    {
        return $this->hasMany(DeliverableReferenceFile::class);
    }

    /**
     * Lo que de verdad se muestra/descarga — nunca los desactivados (ver
     * removeReferenceFile(): "quitar" nunca borra la fila ni el archivo
     * físico, solo is_active = false).
     */
    public function activeReferenceFiles(): HasMany
    {
        return $this->referenceFiles()->where('is_active', true);
    }

    public function isCrossCutting(): bool
    {
        return $this->activity_id === null;
    }

    /**
     * Un docente solo puede guardar borrador, adjuntar archivos, agregar
     * enlaces o enviar una evidencia mientras el periodo académico del
     * entregable está Activo — RF-009 nombra 4 estados (planeación,
     * activo, cerrado, archivado) sin detallar el comportamiento de cada
     * uno salvo "cerrado"; se resolvió que "planeación" (el periodo aún
     * se está configurando: distribución, actividades, líderes) y
     * "archivado" tampoco deben permitir cargar evidencias, igual que
     * "cerrado". Esto es solo sobre acciones de escritura — ver
     * evidencias asignadas sigue funcionando en cualquier estado (RF-045).
     */
    public function acceptsEvidenceSubmissions(): bool
    {
        return $this->academicPeriod->status === AcademicPeriodStatus::Active;
    }

    /**
     * "Cierre" (closes_at) es opcional y, a diferencia de due_at (que solo
     * dispara el estado "Vencido" vía evidences:mark-overdue, sin bloquear
     * nada), es el único corte real de envío — por eso existe como campo
     * aparte. Antes de este método no se leía en ningún punto del flujo de
     * envío: un entregable con fecha de cierre mostrada en pantalla
     * igual aceptaba envíos días después, sin aviso. Ver
     * EvidenceWorkspace::submit(), que además permite reenviar tras una
     * devolución aunque ya haya pasado el cierre — una corrección pedida
     * por el propio líder no debería quedar varada por el plazo que
     * motivó la devolución.
     */
    public function isPastClosingDate(): bool
    {
        return $this->closes_at !== null && now()->gt($this->closes_at);
    }

    /**
     * Extensiones realmente aceptadas al validar un archivo subido: las
     * configuradas explícitamente, o DEFAULT_ALLOWED_FILE_TYPES si el
     * campo quedó vacío. Nunca hay que leer `allowed_file_types` a secas
     * para decidir la regla `mimes:` — ver EvidenceWorkspace::fileValidationRules().
     */
    public function effectiveAllowedFileTypes(): array
    {
        return ! empty($this->allowed_file_types) ? $this->allowed_file_types : self::DEFAULT_ALLOWED_FILE_TYPES;
    }

    /**
     * "(máx. 1, 10MB c/u, formatos: .pdf)" — único punto de formato para
     * el resumen de restricciones de archivo, para no repetir a mano la
     * concatenación (y su espaciado) en cada vista que lo muestre.
     */
    protected function fileConstraintsLabel(): Attribute
    {
        return Attribute::get(function () {
            $parts = [
                "máx. {$this->max_files}",
                "{$this->max_file_size_mb}MB c/u",
            ];

            if (! empty($this->allowed_file_types)) {
                $extensions = collect($this->allowed_file_types)->map(fn ($extension) => ".{$extension}")->join(', ');
                $parts[] = "formatos: {$extensions}";
            }

            return '('.implode(', ', $parts).')';
        });
    }

    /**
     * Creates a pending Evidence record for every recipient that doesn't
     * already have one. Never removes evidence for a recipient that was
     * later dropped from the list — evidence history is never deleted.
     *
     * Notifica al docente solo cuando la Evidence es realmente nueva
     * (`wasRecentlyCreated`) — un re-guardado del mismo entregable para
     * alguien que ya era destinatario (ej. Coordinación solo cambió la
     * fecha límite) nunca debe volver a avisarle como si fuera nuevo.
     */
    public function ensureEvidencesForRecipients(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $evidence = Evidence::firstOrCreate(
                ['deliverable_id' => $this->id, 'user_id' => $userId],
                ['status' => EvidenceStatus::Pending]
            );

            if ($evidence->wasRecentlyCreated) {
                $evidence->user->notify(new EvidenceAssignedNotification($evidence));
            }
        }
    }

    /**
     * RF-038: quién puede descargar un archivo de referencia de este
     * entregable. Administrador/Coordinación/Auditor siempre; el docente
     * si es destinatario; el líder si puede revisar al menos una
     * evidencia de este entregable dentro de su ámbito vigente —
     * reutiliza Evidence::isReviewableBy() en vez de inventar un segundo
     * criterio de "ámbito": es el mismo que ya usa el resto del sistema
     * (Evidence::matchingTeacherAssignment() + User::canLeadAssignment()).
     */
    public function isReferenceFileViewableBy(User $user): bool
    {
        if ($user->hasAnyRole([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor])) {
            return true;
        }

        if ($this->recipients->contains('id', $user->id)) {
            return true;
        }

        if ($user->hasRole(RoleName::Leader)) {
            return $this->evidences->contains(fn (Evidence $evidence) => $evidence->isReviewableBy($user));
        }

        return false;
    }

    /**
     * Guarda el archivo real en el disco 'local' (privado, el mismo que
     * EvidenceFile — ver config/filesystems.php) con nombre aleatorio
     * (UploadedFile::store()), crea la fila y deja el registro de
     * auditoría — "adjuntar" y "reemplazar" (quitar uno + adjuntar otro)
     * usan este mismo método.
     */
    public function attachReferenceFile(UploadedFile $file, User $actor): DeliverableReferenceFile
    {
        $storedPath = $file->store('deliverables/'.$this->id, 'local');

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
     * "Quitar" nunca borra la fila ni el archivo físico — solo
     * is_active = false, mismo principio que el resto del proyecto
     * (catálogos, cuentas de usuario). El archivo real queda en disco por
     * si hiciera falta recuperarlo a mano; deja de listarse y de poder
     * descargarse (DeliverableReferenceFilePolicy exige is_active).
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
