<?php

namespace App\Models;

use App\Enums\EvidenceStatus;
use App\Enums\ReviewDecision;
use App\Enums\RoleName;
use App\Models\Concerns\Auditable;
use App\Notifications\Evidence\EvidenceApprovalConfirmedNotification;
use App\Notifications\Evidence\EvidenceApprovedNotification;
use App\Notifications\Evidence\EvidenceExemptedNotification;
use App\Notifications\Evidence\EvidenceExemptionConfirmedNotification;
use App\Notifications\Evidence\EvidenceNeedsAdjustmentNotification;
use App\Notifications\Evidence\EvidencePendingReviewNotification;
use App\Notifications\Evidence\EvidenceReturnConfirmedNotification;
use App\Notifications\Evidence\EvidenceSubmissionConfirmedNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class Evidence extends Model
{
    use Auditable, HasFactory;

    protected $table = 'evidences';

    protected $fillable = [
        'deliverable_id',
        'user_id',
        'status',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvidenceStatus::class,
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(EvidenceVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EvidenceVersion::class)->orderBy('version_number');
    }

    /**
     * Returns the version the docente should keep editing: the existing
     * current version if it hasn't been submitted yet, or a brand new one
     * otherwise. Each adjustment-and-resend cycle produces a new version;
     * the previous one is never modified or lost (see business rules in
     * [[project-sigae-uts-stack]] Módulo 5/6).
     */
    public function startOrGetDraftVersion(User $actor): EvidenceVersion
    {
        if ($this->currentVersion && $this->currentVersion->submitted_at === null) {
            return $this->currentVersion;
        }

        $nextVersionNumber = ($this->versions()->max('version_number') ?? 0) + 1;

        $version = $this->versions()->create([
            'version_number' => $nextVersionNumber,
            'created_by' => $actor->id,
        ]);

        $this->update([
            'current_version_id' => $version->id,
            'status' => EvidenceStatus::Draft,
        ]);

        // The initial "if" check above already cached a (null) currentVersion
        // relation lookup on this instance; update() doesn't invalidate it,
        // so without this the caller would see a stale null despite the
        // current_version_id column now pointing at the new version.
        $this->setRelation('currentVersion', $version);

        return $version;
    }

    public function submitCurrentVersion(): void
    {
        $this->currentVersion->update(['submitted_at' => now()]);
        $this->update(['status' => EvidenceStatus::Submitted]);

        $this->user->notify(new EvidenceSubmissionConfirmedNotification($this));
        Notification::send($this->reviewerRecipients(), new EvidencePendingReviewNotification($this));
    }

    /**
     * Registra la aprobación de una revisión: crea el Review (con su
     * observación opcional) y mueve el estado, todo en un único punto —
     * antes vivía inline en ReviewShow::approve(), igual que
     * returnCurrentReviewForAdjustment() reemplaza a
     * ReviewShow::returnForAdjustment(). Se centraliza aquí para que las
     * 5 transiciones de estado (enviar, aprobar, devolver, eximir, quitar
     * exención) vivan todas en el modelo, en vez de que dos de ellas
     * queden como excepción sin justificación aparente.
     */
    public function approveCurrentReview(User $reviewer, ?string $observation = null): void
    {
        $this->recordReview($reviewer, ReviewDecision::Approved, $observation);
        $this->update(['status' => EvidenceStatus::Approved]);

        $reviewer->notify(new EvidenceApprovalConfirmedNotification($this));
        $this->user->notify(new EvidenceApprovedNotification($this));
    }

    public function returnCurrentReviewForAdjustment(User $reviewer, string $observation): void
    {
        $this->recordReview($reviewer, ReviewDecision::Returned, $observation);
        $this->update(['status' => EvidenceStatus::NeedsAdjustment]);

        $reviewer->notify(new EvidenceReturnConfirmedNotification($this));
        $this->user->notify(new EvidenceNeedsAdjustmentNotification($this));
    }

    private function recordReview(User $reviewer, ReviewDecision $decision, ?string $observation): void
    {
        $review = $this->currentVersion->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'decision' => $decision,
            'decided_at' => now(),
        ]);

        if (filled($observation)) {
            $review->observations()->create(['body' => $observation]);
        }
    }

    /**
     * Excepción administrativa: el docente deja de estar obligado a este
     * entregable (no cuenta en su % de avance, ver ComplianceCalculator) y
     * no puede editarla/enviarla mientras la exención esté activa. Exige
     * justificación y queda registrada en la bitácora de auditoría además
     * del registro automático de "updated" que ya produce Auditable — este
     * segundo registro es intencional: captura el "por qué", que el diff
     * genérico de campos no puede expresar.
     */
    public function markExempt(string $justification): void
    {
        $previousStatus = $this->status;

        $this->update(['status' => EvidenceStatus::Exempt]);

        AuditLog::record('evidence_marked_exempt', $this, [
            'justification' => $justification,
            'previous_status' => $previousStatus->value,
        ]);

        // auth()->user() ya es la fuente del actor para el registro de
        // auditoría de arriba (vía AuditLog::record() -> auth()->id());
        // se reutiliza aquí por el mismo motivo, en vez de agregar un
        // parámetro $actor que solo serviría para esto.
        if (auth()->check()) {
            auth()->user()->notify(new EvidenceExemptionConfirmedNotification($this));
        }

        $this->user->notify(new EvidenceExemptedNotification($this));
    }

    /**
     * Deshace una exención — nunca se edita el estado directamente, solo a
     * través de esta acción explícita, para que quede su propio rastro de
     * auditoría. La evidencia vuelve a "pendiente": si la fecha límite ya
     * pasó, el próximo `evidences:mark-overdue` la marcará vencida de
     * nuevo, que es el comportamiento correcto.
     */
    public function removeExemption(): void
    {
        $this->update(['status' => EvidenceStatus::Pending]);

        AuditLog::record('evidence_exemption_removed', $this);
    }

    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, EvidenceVersion::class, 'evidence_id', 'evidence_version_id')
            ->latest('decided_at');
    }

    /**
     * The teacher_assignment row that ties this evidence's docente to the
     * activity/period/program_unit scope a líder's leadership is checked
     * against (see User::canLeadAssignment()). Cross-cutting evidence has
     * no activity, so it has no such scope — only Admin/Coordinación
     * review it.
     */
    public function matchingTeacherAssignment(): ?TeacherAssignment
    {
        if ($this->deliverable->isCrossCutting()) {
            return null;
        }

        return TeacherAssignment::where('user_id', $this->user_id)
            ->where('activity_id', $this->deliverable->activity_id)
            ->where('academic_period_id', $this->deliverable->academic_period_id)
            ->first();
    }

    public function isViewableBy(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }

        if ($user->hasAnyRole([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor])) {
            return true;
        }

        if ($user->hasRole(RoleName::Leader)) {
            $assignment = $this->matchingTeacherAssignment();

            return $assignment && $user->canLeadAssignment($assignment);
        }

        return false;
    }

    public function isReviewableBy(User $user): bool
    {
        // Nadie revisa su propia evidencia, incluso si también tiene rol de
        // líder o coordinación sobre su propio ámbito (conflicto de interés).
        if ($user->id === $this->user_id) {
            return false;
        }

        if ($user->hasAnyRole([RoleName::Administrator, RoleName::Coordination])) {
            return true;
        }

        if ($user->hasRole(RoleName::Leader)) {
            $assignment = $this->matchingTeacherAssignment();

            return $assignment && $user->canLeadAssignment($assignment);
        }

        return false;
    }

    /**
     * A quién avisar por correo cuando esta evidencia se envía: los
     * líderes vigentes del ámbito (actividad + programa + periodo) de
     * esta evidencia, igual criterio de vigencia que
     * User::canLeadAssignment(). Si el entregable es transversal (no
     * tiene actividad, luego nunca tiene líder por diseño) o si nadie
     * cubre ese ámbito en este momento, cae a Coordinación — para que
     * ninguna evidencia enviada se quede sin que nadie se entere.
     */
    public function reviewerRecipients(): Collection
    {
        $assignment = $this->matchingTeacherAssignment();

        $leaders = $assignment
            ? User::whereHas('leaderships', fn ($query) => $query
                ->where('academic_period_id', $assignment->academic_period_id)
                ->where('program_unit_id', $assignment->program_unit_id)
                ->where(fn ($q) => $q->whereNull('activity_id')->orWhere('activity_id', $assignment->activity_id))
                ->where('starts_at', '<=', now())
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now())))
                ->get()
            : collect();

        $recipients = $leaders->isEmpty() ? self::coordinationUsers() : $leaders;

        // Defensivo: si la misma persona es a la vez el docente dueño de
        // la evidencia y líder/coordinación de su propio ámbito, no debe
        // notificarse a sí misma como revisora (mismo principio de
        // isReviewableBy(): nadie revisa su propia evidencia).
        return $recipients->reject(fn (User $user) => $user->id === $this->user_id)->values();
    }

    public static function coordinationUsers(): Collection
    {
        return User::whereHas('roles', fn ($query) => $query->where('name', RoleName::Coordination->value))
            ->where('is_active', true)
            ->get();
    }
}
