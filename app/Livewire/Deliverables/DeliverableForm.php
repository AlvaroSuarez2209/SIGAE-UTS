<?php

namespace App\Livewire\Deliverables;

use App\Enums\DeliverableStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Enums\RoleName;
use App\Livewire\Concerns\HasReferenceFileUploads;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\DeliverableTemplate;
use App\Models\User;
use App\Rules\HttpUrl;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class DeliverableForm extends Component
{
    use HasReferenceFileUploads, WithFileUploads;

    public ?Deliverable $deliverable = null;

    /**
     * Bug "el filtro de periodo se pierde al pulsar Volver" (mismo
     * patrón que ReviewShow, commit 3be7623): esta pantalla nunca decide
     * su valor, solo lo recibe por la URL (puesto ahí por el enlace
     * "Nuevo entregable"/"Editar" de DeliverableIndex) y lo reenvía tal
     * cual en "Volver"/"Cancelar" y en los redirects de
     * saveAsDraft()/save().
     */
    #[Url]
    public ?int $periodFilter = null;

    public function deliverablesIndexUrl(): string
    {
        return route('deliverables.index', ['periodFilter' => $this->periodFilter ?? '']);
    }

    public string $scope_type = 'activity';

    public ?int $academic_period_id = null;

    public ?int $activity_id = null;

    public ?int $cross_cutting_commitment_id = null;

    public ?int $template_id = null;

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    public string $completion_criteria = '';

    /**
     * Punto 5 (renombrado en la corrección del punto B: "formato
     * institucional" -> "referencia"): enlace de referencia — se copia
     * desde la plantilla al elegirla (ver updatedTemplateId()) y se
     * limpia al volver a "sin plantilla", igual que los demás campos
     * copiados.
     */
    public string $reference_url = '';

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
     * RF-038: archivo(s) de referencia nuevos, pendientes de subir al
     * confirmar (save()/saveAsDraft()) — el Deliverable puede no existir
     * todavía en los primeros pasos del asistente, así que no se pueden
     * guardar antes de eso (ver attachReferenceFile(), que exige un
     * $this->deliverable ya persistido). Los ya existentes (edición) no
     * viven en una propiedad aparte: render() los lee frescos de
     * $this->deliverable->activeReferenceFiles cada vez. La propiedad
     * $newReferenceFiles y su validación viven en
     * HasReferenceFileUploads, compartido con TemplateForm (punto C).
     *
     * Punto C de las correcciones post-prueba: ids de los archivos de
     * referencia de la plantilla elegida, "heredados" al entregable que
     * se está creando — se muestran en la lista junto a los ya
     * guardados y los recién subidos, marcados como heredados, con
     * opción de quitarlos (removeInheritedReferenceFile()) antes de
     * guardar. Solo se duplican de verdad (ver
     * copyReferenceFileFromTemplate()) los que sigan aquí al guardar —
     * si el usuario quita uno heredado, no se copia. Se reemplazan por
     * completo (nunca se combinan) cada vez que cambia la plantilla
     * elegida; lo que el usuario ya subió a mano en $newReferenceFiles
     * nunca se toca. Se llama *Ids (no inheritedReferenceFiles a
     * secas) para no chocar con la clave de datos de vista del mismo
     * nombre en render() — esa sí trae los modelos completos.
     */
    public array $inheritedReferenceFileIds = [];

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
            $this->reference_url = (string) $this->deliverable->reference_url;
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
            // Punto 11 de la revisión de la directora: antes esto siempre
            // forzaba 'subset' al editar, sin importar el ámbito real —
            // guardar sin tocar el radio habría degradado en silencio un
            // entregable 'all' a 'specific' en normalizeData(). Ahora
            // refleja el recipient_mode persistido.
            $this->recipient_mode = $this->deliverable->recipient_mode === 'all' ? 'all' : 'subset';
            $this->periodLocked = in_array($this->deliverable->academicPeriod->status->value, ['closed', 'archived']);
            // Edición: los datos ya existen y son válidos (o el formulario
            // está bloqueado por completo si el periodo cerró), así que no
            // tiene sentido forzar una progresión lineal paso a paso.
            $this->maxStepReached = self::TOTAL_STEPS;
        } else {
            $this->periodicity_type = PeriodicityType::Single->value;
        }
    }

    /**
     * BUG (revisión de la directora, punto 10): volver a "sin plantilla"
     * hacía return aquí sin limpiar nada, dejando pegados los 11 campos que
     * el bloque de abajo copia — reproducible con plantilla → avanzar →
     * volver → "sin plantilla" → avanzar. Se restablecen a los mismos
     * valores que tendrían si el componente montara sin plantilla desde el
     * principio (ver mount(), rama sin $deliverable).
     */
    public function updatedTemplateId(): void
    {
        if (! $this->template_id) {
            $this->name = '';
            $this->description = '';
            $this->instructions = '';
            $this->completion_criteria = '';
            $this->reference_url = '';
            $this->inheritedReferenceFileIds = [];
            $this->is_mandatory = $this->defaultIsMandatory();
            $this->periodicity_type = PeriodicityType::Single->value;
            $this->allowed_evidence_types = [];
            $this->allowed_file_types = [];
            $this->max_files = 1;
            $this->max_file_size_mb = 10;
            $this->weight_percentage = null;

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
        $this->reference_url = (string) $template->reference_url;
        // Cambiar de una plantilla a otra reemplaza por completo lo
        // heredado de la anterior — nunca se combinan.
        $this->inheritedReferenceFileIds = $template->activeReferenceFiles()->pluck('id')->all();
        $this->is_mandatory = $template->is_mandatory;
        $this->periodicity_type = $template->periodicity_type->value;
        $this->allowed_evidence_types = $template->allowed_evidence_types;
        $this->allowed_file_types = $template->allowed_file_types ?? [];
        $this->max_files = $template->max_files;
        $this->max_file_size_mb = $template->max_file_size_mb;
        $this->weight_percentage = $template->weight_percentage;
    }

    public function removeInheritedReferenceFile(int $templateFileId): void
    {
        $this->inheritedReferenceFileIds = array_values(array_diff($this->inheritedReferenceFileIds, [$templateFileId]));
    }

    public function updatedScopeType(): void
    {
        $this->activity_id = null;
        $this->cross_cutting_commitment_id = null;
        $this->recipient_ids = [];
        $this->resetErrorBag('recipient_ids');
    }

    /**
     * Cuadro de seguimiento, punto 10: "que el docente seleccionado no se
     * pierda al cambiar el periodo o la actividad en el formulario".
     * Antes, cambiar de actividad vaciaba recipient_ids por completo,
     * incluso si el docente ya seleccionado seguía siendo un candidato
     * válido en la nueva actividad (ej. un docente asignado a dos
     * actividades del mismo periodo). Ahora solo se quitan los ids que
     * de verdad dejan de ser candidatos (ver pruneRecipientIdsToCandidates()).
     */
    public function updatedActivityId(): void
    {
        $this->recipient_ids = $this->pruneRecipientIdsToCandidates();
        $this->resetErrorBag('recipient_ids');
        $this->is_mandatory = $this->defaultIsMandatory();
    }

    /**
     * Punto 4 de la revisión de la directora: el valor inicial de
     * is_mandatory se toma de la actividad elegida (se puede editar después
     * en el paso 4) — nunca al revés, ComplianceCalculator sigue sin leer
     * Activity::is_mandatory (ver test de regresión en ComplianceCalculatorTest).
     */
    private function defaultIsMandatory(): bool
    {
        if ($this->scope_type === 'activity' && $this->activity_id) {
            return Activity::find($this->activity_id)?->is_mandatory ?? true;
        }

        return true;
    }

    /**
     * Mismo bug del diagnóstico anterior (ver updatedActivityId()), visto
     * al revés: cambiar de PERIODO nunca tocaba recipient_ids, dejando
     * ids de docentes que ya no son candidatos (sin TeacherAssignment en
     * el periodo nuevo) seleccionados de forma invisible — su checkbox ya
     * no aparece en la lista, que se filtra por candidateTeachers(), así
     * que si el usuario no volvía al paso 5 a notarlo, save()/saveAsDraft()
     * los guardaba igual como destinatarios inválidos para ese ámbito.
     */
    public function updatedAcademicPeriodId(): void
    {
        $this->recipient_ids = $this->pruneRecipientIdsToCandidates();
        $this->resetErrorBag('recipient_ids');
    }

    /**
     * Quita de recipient_ids solo los que de verdad dejaron de ser
     * candidatos para el ámbito (actividad + periodo, o transversal)
     * actual — nunca un vaciado completo, para no perder una selección
     * que sigue siendo válida tras el cambio.
     */
    private function pruneRecipientIdsToCandidates(): array
    {
        $candidateIds = $this->candidateTeachers()->pluck('id')->all();

        return array_values(array_intersect($this->recipient_ids, $candidateIds));
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

    protected function referenceFileOwner(): Deliverable|DeliverableTemplate|null
    {
        return $this->deliverable;
    }

    /**
     * Cada archivo heredado todavía en la lista ocupa un cupo del tope
     * (DeliverableReferenceFile::MAX_FILES) aunque no sea una fila real
     * todavía — se copiará al guardar si sigue presente.
     */
    protected function reservedReferenceFileSlots(): int
    {
        return count($this->inheritedReferenceFileIds);
    }

    /**
     * Sobre un archivo YA guardado (edición) — a diferencia de los
     * pendientes de arriba, esto actúa de inmediato (is_active = false +
     * auditoría), no espera a save()/saveAsDraft(). No exige Gate propio:
     * toda la pantalla ya está detrás de 'role:administrator,coordination'
     * (routes/web.php), igual que el resto de las acciones de este
     * componente.
     */
    public function removeReferenceFile(DeliverableReferenceFile $referenceFile): void
    {
        $this->deliverable->removeReferenceFile($referenceFile, auth()->user());
    }

    private function candidateTeachers()
    {
        if ($this->scope_type === 'activity' && $this->activity_id && $this->academic_period_id) {
            return User::whereHas('teacherAssignments', fn ($q) => $q
                ->where('activity_id', $this->activity_id)
                ->where('academic_period_id', $this->academic_period_id)
            )->orderByAccentInsensitive('name')->get();
        }

        if ($this->scope_type === 'cross_cutting') {
            return User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
                ->orderByAccentInsensitive('name')
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
                'reference_url' => ['nullable', 'string', 'max:2048', new HttpUrl],
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
        $data['reference_url'] = $data['reference_url'] ?: null;
        $data['closes_at'] = $data['closes_at'] ?: null;
        $data['allowed_file_types'] = $data['allowed_file_types'] ?: null;
        $data['deliverable_template_id'] = $this->template_id;
        // Punto 11 de la revisión de la directora: persiste el ámbito real
        // ('all' = todos los docentes que cumplan el ámbito en cada
        // momento) para que TeacherAssignmentObserver sepa a qué
        // entregables sumar un docente nuevo sin que nadie reabra el
        // formulario. El property local sigue usando 'subset' (nombre del
        // radio button del paso 5); se traduce aquí al vocabulario
        // persistido ('all'/'specific').
        $data['recipient_mode'] = $this->recipient_mode === 'all' ? 'all' : 'specific';
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

        $isNewDeliverable = ! $this->deliverable;

        if ($this->deliverable) {
            $this->deliverable->update($data);
        } else {
            $this->deliverable = Deliverable::create($data);
        }

        // Punto 3 (revisión de perfil Coordinación): "seleccionar un
        // docente, guardar como borrador y reabrirlo pierde la
        // selección" — saveAsDraft() nunca escribía recipient_ids en
        // deliverable_recipients (solo save() lo hacía), así que
        // mount() siempre la encontraba vacía al reabrir. Se sincroniza
        // igual que en save() (mismo criterio: "all" resuelve a quienes
        // cumplen el ámbito ahora), pero sin ensureEvidencesForRecipients()
        // — eso sigue siendo exclusivo de la publicación real, para que
        // ningún docente vea ni pueda actuar sobre nada mientras el
        // entregable siga en Borrador.
        $recipientIds = $this->recipient_mode === 'all'
            ? $this->candidateTeachers()->pluck('id')->all()
            : $this->recipient_ids;

        $this->deliverable->recipients()->sync($recipientIds);

        $this->persistNewReferenceFiles();
        $this->copyReferenceFileFromTemplate($isNewDeliverable);

        session()->flash('status', 'Entregable guardado como borrador. No es visible para los docentes todavía.');

        $this->redirect($this->deliverablesIndexUrl(), navigate: false);
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

        $isNewDeliverable = ! $this->deliverable;

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

        $this->persistNewReferenceFiles();
        $this->copyReferenceFileFromTemplate($isNewDeliverable);

        session()->flash('status', 'Entregable guardado correctamente.');

        $this->redirect($this->deliverablesIndexUrl(), navigate: false);
    }

    /**
     * Sube y adjunta los archivos de referencia pendientes — llamado
     * siempre DESPUÉS de crear/actualizar $this->deliverable, nunca antes
     * (attachReferenceFile() exige un entregable ya persistido).
     */
    private function persistNewReferenceFiles(): void
    {
        foreach ($this->newReferenceFiles as $file) {
            $this->deliverable->attachReferenceFile($file, auth()->user());
        }

        $this->newReferenceFiles = [];
    }

    /**
     * Punto 5 (ampliado en el punto C: ahora son los que el usuario
     * dejó en $inheritedReferenceFileIds, no "todos los de la
     * plantilla" — si quitó uno de la lista antes de guardar, ese no
     * se copia). Al CREAR (nunca al editar — template_id no se
     * restaura en edición, ver mount()) un entregable a partir de una
     * plantilla, duplica cada archivo de referencia todavía heredado
     * como una fila nueva propia de este entregable — comparte
     * disk_path (el archivo físico es el mismo, no hace falta
     * copiarlo) pero necesita su propio stored_name único (constraint
     * de BD), que no se usa para nada más que esa unicidad.
     */
    private function copyReferenceFileFromTemplate(bool $isNewDeliverable): void
    {
        if (! $isNewDeliverable || empty($this->inheritedReferenceFileIds)) {
            return;
        }

        $templateFiles = DeliverableReferenceFile::whereIn('id', $this->inheritedReferenceFileIds)->get();

        foreach ($templateFiles as $templateFile) {
            $this->deliverable->referenceFiles()->create([
                'original_name' => $templateFile->original_name,
                'stored_name' => Str::random(40).'.'.pathinfo($templateFile->stored_name, PATHINFO_EXTENSION),
                'disk_path' => $templateFile->disk_path,
                'mime_type' => $templateFile->mime_type,
                'size_bytes' => $templateFile->size_bytes,
                'uploaded_by' => auth()->id(),
            ]);
        }
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
                ->orderedByHierarchy()
                ->get(),
            'commitments' => CrossCuttingCommitment::where('is_active', true)
                ->orWhere('id', $this->cross_cutting_commitment_id)
                ->orderByAccentInsensitive('name')
                ->get(),
            'templates' => DeliverableTemplate::where('is_active', true)
                ->orWhere('id', $this->template_id)
                ->orderByAccentInsensitive('name')
                ->get(),
            'periodicityOptions' => PeriodicityType::cases(),
            'evidenceTypeOptions' => EvidenceType::cases(),
            'fileTypeOptions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'zip'],
            // Consultado explícito, no vía la relación directo en la vista:
            // Model::preventLazyLoading() está activo en local y tronaría
            // si la vista accediera a $deliverable->activeReferenceFiles
            // sin que ya viniera cargada.
            'referenceFiles' => $this->deliverable?->activeReferenceFiles()->get() ?? collect(),
            'inheritedReferenceFiles' => $this->inheritedReferenceFileIds
                ? DeliverableReferenceFile::whereIn('id', $this->inheritedReferenceFileIds)->get()
                : collect(),
            'candidateTeachers' => $this->candidateTeachers(),
            'totalSteps' => self::TOTAL_STEPS,
        ])->title($this->deliverable ? 'Editar entregable' : 'Nuevo entregable');
    }
}
