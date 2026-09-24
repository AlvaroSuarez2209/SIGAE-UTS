<?php

namespace App\Livewire\Deliverables;

use App\Models\DeliverableTemplate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Plantillas de entregables')]
class TemplateIndex extends Component
{
    public function toggleActive(DeliverableTemplate $template): void
    {
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function render()
    {
        return view('livewire.deliverables.template-index', [
            'templates' => DeliverableTemplate::orderBy('name')->get(),
        ]);
    }
}
