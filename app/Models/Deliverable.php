<?php

namespace App\Models;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\Concerns\Auditable;
use App\Notifications\Evidence\EvidenceAssignedNotification;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
