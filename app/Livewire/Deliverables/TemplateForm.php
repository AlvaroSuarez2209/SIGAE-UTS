<?php

namespace App\Livewire\Deliverables;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\DeliverableTemplate;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Rules\HttpUrl;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class TemplateForm extends Component
{
    use WithFileUploads;

    public ?DeliverableTemplate $template = null;

    /**
     * Mismo patrón que las demás pantallas: solo recibe estos filtros
     * por la URL (puestos ahí por los enlaces de TemplateIndex) y los
     * reenvía en "Cancelar" y en el redirect de save().
     */
    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public function templatesIndexUrl(): string
    {
        return route('deliverable-templates.index', ['search' => $this->search, 'statusFilter' => $this->statusFilter]);
    }

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    public string $completion_criteria = '';

    /**
     * Punto 5 (renombrado en la corrección del punto B: "formato
     * institucional" -> "referencia"): enlace de referencia de la
     * plantilla — se copia a cada entregable creado a partir de ella
     * (ver DeliverableForm::updatedTemplateId()).
     */
    public string $reference_url = '';

    /**
     * Archivo de referencia de la plantilla, pendiente de subir al
     * confirmar — a diferencia de los "Archivo(s) de referencia" de un
     * Deliverable (hasta 3), una plantilla admite exactamente uno: no
     * hay otro concepto de archivo de plantilla además de este. La
     * plantilla puede no existir todavía (creación), así que no se
     * puede guardar antes de eso (ver attachReferenceFile(), que exige
     * un $this->template ya persistido).
     */
    public $newReferenceFile = null;

    public bool $is_mandatory = true;

    public string $periodicity_type = '';

    public array $allowed_evidence_types = [];

    public array $allowed_file_types = [];

    public int $max_files = 1;

    public int $max_file_size_mb = 10;

    public ?int $weight_percentage = null;

    public bool $is_active = true;

    public function mount(?DeliverableTemplate $deliverableTemplate = null): void
    {
        $this->template = $deliverableTemplate?->exists ? $deliverableTemplate : null;

        if ($this->template) {
            $this->name = $this->template->name;
            $this->description = (string) $this->template->description;
            $this->instructions = (string) $this->template->instructions;
            $this->completion_criteria = (string) $this->template->completion_criteria;
            $this->reference_url = (string) $this->template->reference_url;
            $this->is_mandatory = $this->template->is_mandatory;
            $this->periodicity_type = $this->template->periodicity_type->value;
            $this->allowed_evidence_types = $this->template->allowed_evidence_types;
            $this->allowed_file_types = $this->template->allowed_file_types ?? [];
            $this->max_files = $this->template->max_files;
            $this->max_file_size_mb = $this->template->max_file_size_mb;
            $this->weight_percentage = $this->template->weight_percentage;
            $this->is_active = $this->template->is_active;
        } else {
            $this->periodicity_type = PeriodicityType::Single->value;
        }
    }

    /**
     * Valida el archivo apenas se adjunta — mismo criterio que
     * DeliverableForm::updatedNewReferenceFiles(). Una plantilla admite
     * exactamente uno: si ya tiene uno guardado, el dropzone ni
     * siquiera se muestra (ver template-form.blade.php).
     */
    public function updatedNewReferenceFile(): void
    {
        $this->resetErrorBag(['newReferenceFile']);

        $validator = Validator::make(['newReferenceFile' => $this->newReferenceFile], [
            'newReferenceFile' => [
                'file',
                'max:'.(DeliverableReferenceFile::MAX_FILE_SIZE_MB * 1024),
                'mimes:'.implode(',', Deliverable::DEFAULT_ALLOWED_FILE_TYPES),
            ],
        ]);

        if ($validator->fails()) {
            $this->addError('newReferenceFile', $validator->errors()->first('newReferenceFile'));
            $this->newReferenceFile = null;
        }
    }

    /**
     * Sobre el archivo YA guardado (edición) — actúa de inmediato
     * (is_active = false + auditoría), mismo criterio que
     * DeliverableForm::removeReferenceFile().
     */
    public function removeReferenceFile(DeliverableReferenceFile $referenceFile): void
    {
        $this->template->removeReferenceFile($referenceFile, auth()->user());
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('deliverable_templates'))->ignore($this->template)],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'completion_criteria' => ['nullable', 'string', 'max:2000'],
            'reference_url' => ['nullable', 'string', 'max:2048', new HttpUrl],
            'is_mandatory' => ['boolean'],
            'periodicity_type' => ['required', 'in:'.implode(',', array_column(PeriodicityType::cases(), 'value'))],
            'allowed_evidence_types' => ['required', 'array', 'min:1'],
            'allowed_evidence_types.*' => ['in:'.implode(',', array_column(EvidenceType::cases(), 'value'))],
            'allowed_file_types' => ['nullable', 'array'],
            'max_files' => ['required', 'integer', 'min:1', 'max:20'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'weight_percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $data['description'] = $data['description'] ?: null;
        $data['instructions'] = $data['instructions'] ?: null;
        $data['completion_criteria'] = $data['completion_criteria'] ?: null;
        $data['reference_url'] = $data['reference_url'] ?: null;
        $data['allowed_file_types'] = $data['allowed_file_types'] ?: null;

        if ($this->template) {
            $this->template->update($data);
        } else {
            $this->template = DeliverableTemplate::create($data);
        }

        if ($this->newReferenceFile) {
            $this->template->attachReferenceFile($this->newReferenceFile, auth()->user());
            $this->newReferenceFile = null;
        }

        session()->flash('status', 'Plantilla guardada correctamente.');

        $this->redirect($this->templatesIndexUrl(), navigate: false);
    }

    public function render()
    {
        return view('livewire.deliverables.template-form', [
            'periodicityOptions' => PeriodicityType::cases(),
            'evidenceTypeOptions' => EvidenceType::cases(),
            'fileTypeOptions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'zip'],
            'referenceFiles' => $this->template?->activeReferenceFiles()->get() ?? collect(),
        ])->title($this->template ? 'Editar plantilla' : 'Nueva plantilla');
    }
}
