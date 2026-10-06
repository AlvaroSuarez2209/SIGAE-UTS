<?php

namespace App\Livewire\Deliverables;

use App\Enums\DeliverableStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\DeliverableTemplate;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DeliverableForm extends Component
{
    public ?Deliverable $deliverable = null;

    public string $scope_type = 'activity';

    public ?int $academic_period_id = null;

    public ?int $activity_id = null;

    public ?int $cross_cutting_commitment_id = null;

    public ?int $template_id = null;

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    public string $completion_criteria = '';

    public bool $is_mandatory = true;

    public string $periodicity_type = '';

    public string $opens_at = '';

    public string $due_at = '';

    public string $closes_at = '';

    public array $allowed_evidence_types = [];

    public array $allowed_file_types = [];

    public int $max_files = 1;

    public int $max_file_size_mb = 10;

    public ?int $weight_percentage = null;

    public string $recipient_mode = 'all';

    public array $recipient_ids = [];

    public bool $periodLocked = false;

    /**
     * Prioridad 5: wizard de 6 pasos sobre el mismo componente/formulario
     * de siempre — el estado del borrador vive únicamente aquí, en las
     * propiedades públicas del componente, nunca en BD (ver diagnóstico:
     * un Deliverable incomplete violaría su propio CHECK de "un solo
     * ámbito" y dispararía sync()/ensureEvidencesForRecipients() con datos
     * a medias). Avanzar de paso no persiste nada; solo save() lo hace.
     */
    public const TOTAL_STEPS = 6;

    public int $step = 1;

    public int $maxStepReached = 1;

    public function mount(?Deliverable $deliverable = null): void
    {
        $this->deliverable = $deliverable?->exists ? $deliverable : null;

        if ($this->deliverable) {
            $this->scope_type = $this->deliverable->isCrossCutting() ? 'cross_cutting' : 'activity';
            $this->academic_period_id = $this->deliverable->academic_period_id;
            $this->activity_id = $this->deliverable->activity_id;
            $this->cross_cutting_commitment_id = $this->deliverable->cross_cutting_commitment_id;
            $this->name = $this->deliverable->name;
            $this->description = (string) $this->deliverable->description;
            $this->instructions = (string) $this->deliverable->instructions;
            $this->completion_criteria = (string) $this->deliverable->completion_criteria;
            $this->is_mandatory = $this->deliverable->is_mandatory;
            $this->periodicity_type = $this->deliverable->periodicity_type->value;
            $this->opens_at = $this->deliverable->opens_at->format('Y-m-d\TH:i');
            $this->due_at = $this->deliverable->due_at->format('Y-m-d\TH:i');
            $this->closes_at = $this->deliverable->closes_at?->format('Y-m-d\TH:i') ?? '';
            $this->allowed_evidence_types = $this->deliverable->allowed_evidence_types->map->value->all();
            $this->allowed_file_types = $this->deliverable->allowed_file_types ?? [];
            $this->max_files = $this->deliverable->max_files;
            $this->max_file_size_mb = $this->deliverable->max_file_size_mb;
            $this->weight_percentage = $this->deliverable->weight_percentage;
            $this->recipient_ids = $this->deliverable->recipients()->pluck('users.id')->all();
            $this->recipient_mode = 'subset';
            $this->periodLocked = in_array($this->deliverable->academicPeriod->status->value, ['closed', 'archived']);
            // Edición: los datos ya existen y son válidos (o el formulario
            // está bloqueado por completo si el periodo cerró), así que no
            // tiene sentido forzar una progresión lineal paso a paso.
            $this->maxStepReached = self::TOTAL_STEPS;
        } else {
            $this->periodicity_type = PeriodicityType::Single->value;
        }
    }

    public function updatedTemplateId(): void
    {
        if (! $this->template_id) {
            return;
        }

        $template = DeliverableTemplate::find($this->template_id);

        if (! $template) {
            return;
        }

        $this->name = $template->name;
        $this->description = (string) $template->description;
        $this->instructions = (string) $template->instructions;
        $this->completion_criteria = (string) $template->completion_criteria;
        $this->is_mandatory = $template->is_mandatory;
        $this->periodicity_type = $template->periodicity_type->value;
        $this->allowed_evidence_types = $template->allowed_evidence_types;
        $this->allowed_file_types = $template->allowed_file_types ?? [];
        $this->max_files = $template->max_files;
        $this->max_file_size_mb = $template->max_file_size_mb;
        $this->weight_percentage = $template->weight_percentage;
    }

    public function updatedScopeType(): void
    {
        $this->activity_id = null;
        $this->cross_cutting_commitment_id = null;
        $this->recipient_ids = [];
        $this->resetErrorBag('recipient_ids');
    }

    public function updatedActivityId(): void
    {
        $this->recipient_ids = [];
        $this->resetErrorBag('recipient_ids');
    }

    public function updatedAcademicPeriodId(): void
    {
        $this->resetErrorBag('recipient_ids');
    }

    /**
     * El aviso rojo de "no se puede publicar sin destinatarios" (punto 1
     * de la revisión de la directora) solo debe aparecer tras un intento
     * de publicar fallido — si el usuario cambia de modo antes de
     * reintentar, no debe quedar un error viejo compitiendo con el aviso
     * amarillo informativo de "todavía no hay nadie en este ámbito".
     */
    public function updatedRecipientMode(): void
    {
        $this->resetErrorBag('recipient_ids');
    }

    private function candidateTeachers()
    {
        if ($this->scope_type === 'activity' && $this->activity_id && $this->academic_period_id) {
            return User::whereHas('teacherAssignments', fn ($q) => $q
                ->where('activity_id', $this->activity_id)
                ->where('academic_period_id', $this->academic_period_id)
            )->orderBy('name')->get();
        }

        if ($this->scope_type === 'cross_cutting') {
            return User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
                ->orderBy('name')
                ->get();
        }

        return collect();
    }

    /**
     * Reglas agrupadas por paso del wizard, para poder validar solo el
     * paso visible al avanzar ("Siguiente") y el conjunto completo al
     * confirmar (save()) — misma fuente única de verdad en ambos casos,
     * nunca reglas duplicadas o que puedan desincronizarse entre sí.
     */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'academic_period_id' => [
                    'required',
                    Rule::exists('academic_periods', 'id')->whereIn('status', ['planning', 'active']),
                ],
                'scope_type' => ['required', 'in:activity,cross_cutting'],
                'activity_id' => ['required_if:scope_type,activity', 'nullable', 'exists:activities,id'],
                'cross_cutting_commitment_id' => ['required_if:scope_type,cross_cutting', 'nullable', 'exists:cross_cutting_commitments,id'],
            ],
            2 => [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'instructions' => ['nullable', 'string', 'max:5000'],
                'completion_criteria' => ['nullable', 'string', 'max:2000'],
            ],
            3 => [
                'periodicity_type' => ['required', 'in:'.implode(',', array_column(PeriodicityType::cases(), 'value'))],
                'opens_at' => ['required', 'date'],
                'due_at' => ['required', 'date', 'after_or_equal:opens_at'],
                'closes_at' => ['nullable', 'date', 'after_or_equal:due_at'],
            ],
            4 => [
                'allowed_evidence_types' => ['required', 'array', 'min:1'],
                'allowed_evidence_types.*' => ['in:'.implode(',', array_column(EvidenceType::cases(), 'value'))],
                'allowed_file_types' => ['nullable', 'array'],
                'max_files' => ['required', 'integer', 'min:1', 'max:20'],
                'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
                'weight_percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
                'is_mandatory' => ['boolean'],
            ],
            default => [],
        };
    }

    private function validationMessages(): array
    {
        return [
            'academic_period_id.exists' => 'El periodo seleccionado no admite nuevos entregables (está cerrado o archivado).',
            'activity_id.required_if' => 'Selecciona la actividad a la que pertenece este entregable.',
            'cross_cutting_commitment_id.required_if' => 'Selecciona la clasificación del compromiso transversal.',
        ];
    }

    /**
     * El paso 5 (Destinatarios) no tiene reglas de Validator: se exige un
     * destinatario resuelto con una comprobación manual. Esto solo se
     * exige al publicar (save()), nunca al guardar como borrador ni al
     * avanzar de paso en el wizard — un borrador puede guardarse antes de
     * que existan asignaciones docentes que resolver.
     *
     * Cubre 2 casos de "entregable sin ningún destinatario real" (revisión
     * de la directora, punto 1): modo "subset" sin nada marcado, y modo
     * "all" cuando el ámbito elegido no resuelve a ningún docente (ej. una
     * actividad todavía sin asignaciones, o un compromiso transversal sin
     * docentes activos). Antes de este cambio, el segundo caso no se
     * validaba y el entregable quedaba creado sin nadie que pudiera
     * cumplirlo.
     */
    private function validateRecipients(): bool
    {
        $this->resetErrorBag('recipient_ids');

        if ($this->recipient_mode === 'subset' && empty($this->recipient_ids)) {
            $this->addError('recipient_ids', 'Selecciona al menos un docente destinatario.');

            return false;
        }

        if ($this->recipient_mode === 'all' && $this->candidateTeachers()->isEmpty()) {
            $this->addError('recipient_ids', 'Todavía no hay ningún docente que cumpla este ámbito; no se puede publicar sin destinatarios. Puedes guardarlo como borrador mientras tanto.');

            return false;
        }

        return true;
    }

    /**
     * En qué paso vive un campo, para poder devolver al usuario al paso
     * correcto si save()/saveAsDraft() fallan en un campo que ya no es
     * visible (el wizard ya avanzó más allá de ese paso).
     */
    private function stepForField(string $field): int
    {
        $base = explode('.', $field)[0];

        foreach ([1, 2, 3, 4] as $step) {
            if (array_key_exists($base, $this->rulesForStep($step))) {
                return $step;
            }
        }

        return 1;
    }

    public function nextStep(): void
    {
        if ($this->periodLocked) {
            return;
        }

        $rules = $this->rulesForStep($this->step);

        if ($rules !== []) {
            $this->validate($rules, $this->validationMessages());
        }

        $this->step = min($this->step + 1, self::TOTAL_STEPS);
        $this->maxStepReached = max($this->maxStepReached, $this->step);
    }

    public function previousStep(): void
    {
        $this->resetErrorBag();
        $this->step = max($this->step - 1, 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > self::TOTAL_STEPS || $step > $this->maxStepReached) {
            return;
        }

        $this->resetErrorBag();
        $this->step = $step;
    }

    private function validatedCoreData(): array
    {
        try {
            return $this->validate(
                array_merge(
                    $this->rulesForStep(1),
                    $this->rulesForStep(2),
                    $this->rulesForStep(3),
                    $this->rulesForStep(4),
                ),
                $this->validationMessages(),
            );
        } catch (ValidationException $e) {
            $this->step = $this->stepForField(array_key_first($e->errors()));

            throw $e;
        }
    }

    private function normalizeData(array $data): array
    {
        $data['activity_id'] = $data['scope_type'] === 'activity' ? $data['activity_id'] : null;
        $data['cross_cutting_commitment_id'] = $data['scope_type'] === 'cross_cutting' ? $data['cross_cutting_commitment_id'] : null;
        $data['description'] = $data['description'] ?: null;
        $data['instructions'] = $data['instructions'] ?: null;
        $data['completion_criteria'] = $data['completion_criteria'] ?: null;
        $data['closes_at'] = $data['closes_at'] ?: null;
        $data['allowed_file_types'] = $data['allowed_file_types'] ?: null;
        $data['deliverable_template_id'] = $this->template_id;
        unset($data['scope_type']);

        return $data;
    }

    /**
     * Guarda sin publicar: no exige destinatarios resueltos (todavía no
     * importan) y nunca sincroniza destinatarios ni crea evidencia — un
     * entregable en Borrador no debe ser visible ni accionable para
     * ningún docente. Nunca revierte un entregable ya Publicado de vuelta
     * a Borrador (la transición es de un solo sentido); si se llama sobre
     * uno, no hace nada.
     */
    public function saveAsDraft(): void
    {
        if ($this->periodLocked) {
            $this->addError('academic_period_id', 'Este periodo está cerrado y no admite modificaciones ordinarias.');

            return;
        }

        if ($this->deliverable && $this->deliverable->status === DeliverableStatus::Published) {
            return;
        }

        $data = $this->normalizeData($this->validatedCoreData());
        $data['status'] = DeliverableStatus::Draft->value;

        if ($this->deliverable) {
            $this->deliverable->update($data);
        } else {
            $this->deliverable = Deliverable::create($data);
        }

        session()->flash('status', 'Entregable guardado como borrador. No es visible para los docentes todavía.');

        $this->redirect(route('deliverables.index'), navigate: false);
    }

    /**
     * Publica el entregable: exige destinatarios resueltos y dispara los
     * efectos reales (sync de destinatarios + evidencia pendiente por
     * cada uno). Si ya estaba Publicado, lo deja igual; si era un
     * Borrador, esta es la transición que lo activa.
     */
    public function save(): void
    {
        if ($this->periodLocked) {
            $this->addError('academic_period_id', 'Este periodo está cerrado y no admite modificaciones ordinarias.');

            return;
        }

        $data = $this->validatedCoreData();

        if (! $this->validateRecipients()) {
            $this->step = 5;

            return;
        }

        $data = $this->normalizeData($data);
        $data['status'] = DeliverableStatus::Published->value;

        if ($this->deliverable) {
            $this->deliverable->update($data);
        } else {
            $this->deliverable = Deliverable::create($data);
        }

        $recipientIds = $this->recipient_mode === 'all'
            ? $this->candidateTeachers()->pluck('id')->all()
            : $this->recipient_ids;

        $this->deliverable->recipients()->sync($recipientIds);
        $this->deliverable->ensureEvidencesForRecipients($recipientIds);

        session()->flash('status', 'Entregable guardado correctamente.');

        $this->redirect(route('deliverables.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.deliverables.deliverable-form', [
            'periods' => AcademicPeriod::whereIn('status', ['planning', 'active'])
                ->orWhere('id', $this->academic_period_id)
                ->orderByDesc('start_date')
                ->get(),
            'activities' => Activity::with(['component', 'subcomponent'])
                ->where('is_active', true)
                ->orWhere('id', $this->activity_id)
                ->orderBy('name')
                ->get(),
            'commitments' => CrossCuttingCommitment::where('is_active', true)
                ->orWhere('id', $this->cross_cutting_commitment_id)
                ->orderBy('name')
                ->get(),
            'templates' => DeliverableTemplate::where('is_active', true)->orderBy('name')->get(),
            'periodicityOptions' => PeriodicityType::cases(),
            'evidenceTypeOptions' => EvidenceType::cases(),
            'fileTypeOptions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'zip'],
            'candidateTeachers' => $this->candidateTeachers(),
            'totalSteps' => self::TOTAL_STEPS,
        ])->title($this->deliverable ? 'Editar entregable' : 'Nuevo entregable');
    }
}
