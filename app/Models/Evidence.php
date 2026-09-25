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
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Solo el Líder asignado revisa (RS-005, RF-027, RF-056 a RF-059 del
     * documento de alcance: siempre "el líder deberá...", nunca
     * Coordinación ni Administrador — Coordinación "supervisa
     * cumplimiento" y "obtiene informes", no revisa evidencias). Única
     * excepción: los compromisos transversales no tienen actividad, así
     * que estructuralmente nunca pueden tener un líder que los cubra
     * (matchingTeacherAssignment() siempre null para ellos) — para que no
     * queden sin nadie que los apruebe/devuelva, Administrador los revisa
     * (Coordinación sigue sin poder, ni siquiera ahí).
     */
    public function isReviewableBy(User $user): bool
    {
        // Nadie revisa su propia evidencia, incluso si también tiene rol de
        // líder (conflicto de interés).
        if ($user->id === $this->user_id) {
            return false;
        }

        if ($this->deliverable->isCrossCutting()) {
            return $user->hasRole(RoleName::Administrator);
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

    /**
     * Versión "muchas evidencias a la vez" de isReviewableBy() — misma
     * regla de negocio, expresada como JOIN en vez de traer todas las
     * evidencias a PHP y llamar isReviewableBy() evidencia por evidencia
     * (eso disparaba matchingTeacherAssignment() + canLeadAssignment(),
     * 2 consultas nuevas por fila). isReviewableBy() sigue existiendo tal
     * cual para el chequeo de una sola evidencia (EvidencePolicy::review()),
     * donde no tiene sentido montar un JOIN para una fila.
     *
     * Los LEFT JOIN (no INNER) son necesarios porque hay dos caminos
     * independientes para que una fila cuente como revisable — liderazgo
     * vigente (empareja por user_id + activity_id + academic_period_id vía
     * teacher_assignments, luego liderazgo vigente en leaderships: mismo
     * período + programa, actividad-o-null, dentro de starts_at/ends_at) o
     * ser Administrador sobre un compromiso transversal — y ambos deben
     * poder ser ciertos a la vez para alguien con los dos roles (si fueran
     * INNER JOIN, una fila que no matchea el camino de liderazgo se
     * descartaría antes de llegar al WHERE, sin darle chance al camino de
     * Administrador). Si esta regla cambia, hay que actualizar también
     * isReviewableBy().
     *
     * $onlyViaLeaderRole: true excluye el camino de Administrador (aunque
     * $user lo sea) — para cuando interesa específicamente lo que depende
     * del rol Líder de $user, no todo lo que podría revisar por cualquier
     * otro motivo. Dashboard::leaderPanel() lo usa para su contador "en tu
     * ámbito" (no debe mezclar compromisos transversales ahí aunque el
     * líder también sea Administrador), y
     * UserForm::pendingReviewCountForLeader() lo usa para no bloquear
     * quitar el rol Líder por evidencias que en realidad dependen del rol
     * Administrador de esa misma persona.
     */
    public function scopeReviewableBy(Builder $query, User $user, bool $onlyViaLeaderRole = false): Builder
    {
        $viaLeader = $user->hasRole(RoleName::Leader);
        $viaAdminOnCrossCutting = ! $onlyViaLeaderRole && $user->hasRole(RoleName::Administrator);

        if (! $viaLeader && ! $viaAdminOnCrossCutting) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->leftJoin('deliverables', 'deliverables.id', '=', 'evidences.deliverable_id')
            ->leftJoin('teacher_assignments', function ($join) {
                $join->on('teacher_assignments.user_id', '=', 'evidences.user_id')
                    ->on('teacher_assignments.activity_id', '=', 'deliverables.activity_id')
                    ->on('teacher_assignments.academic_period_id', '=', 'deliverables.academic_period_id');
            })
            ->leftJoin('leaderships', function ($join) use ($user) {
                $join->on('leaderships.academic_period_id', '=', 'teacher_assignments.academic_period_id')
                    ->on('leaderships.program_unit_id', '=', 'teacher_assignments.program_unit_id')
                    ->where(function ($q) {
                        $q->whereNull('leaderships.activity_id')
                            ->orWhereColumn('leaderships.activity_id', 'teacher_assignments.activity_id');
                    })
                    ->where('leaderships.user_id', $user->id)
                    ->where('leaderships.starts_at', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('leaderships.ends_at')->orWhere('leaderships.ends_at', '>=', now());
                    });
            })
            ->where(function ($query) use ($viaLeader, $viaAdminOnCrossCutting) {
                if ($viaLeader) {
                    $query->orWhereNotNull('leaderships.id');
                }

                if ($viaAdminOnCrossCutting) {
                    $query->orWhereNull('deliverables.activity_id');
                }
            })
            ->where('evidences.user_id', '!=', $user->id)
            ->select('evidences.*')
            ->distinct();
    }
}
