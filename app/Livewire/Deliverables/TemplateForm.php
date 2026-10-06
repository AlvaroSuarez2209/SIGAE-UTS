<?php

namespace App\Livewire\Deliverables;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\DeliverableTemplate;
use App\Rules\CaseAccentInsensitiveUnique;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TemplateForm extends Component
{
    public ?DeliverableTemplate $template = null;

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    public string $completion_criteria = '';

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

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('deliverable_templates'))->ignore($this->template)],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'completion_criteria' => ['nullable', 'string', 'max:2000'],
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
        $data['allowed_file_types'] = $data['allowed_file_types'] ?: null;

        if ($this->template) {
            $this->template->update($data);
        } else {
            DeliverableTemplate::create($data);
        }

        session()->flash('status', 'Plantilla guardada correctamente.');

        $this->redirect(route('deliverable-templates.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.deliverables.template-form', [
            'periodicityOptions' => PeriodicityType::cases(),
            'evidenceTypeOptions' => EvidenceType::cases(),
            'fileTypeOptions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'zip'],
        ])->title($this->template ? 'Editar plantilla' : 'Nueva plantilla');
    }
}
