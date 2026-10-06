<?php

namespace App\Livewire\Admin;

use App\Models\InstitutionSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Administración > Identidad institucional (Prioridad 3). Solo
 * Administrador puede ver o modificar — autorizado vía
 * InstitutionSettingsPolicy, no solo ocultando el enlace del menú.
 *
 * Dos campos de logo separados, no uno reutilizado — ver
 * App\Models\InstitutionSettings: el logo del login es apaisado y el de
 * marca (sidebar/PDF/correos) es cuadrado; comparten imagen deformaría uno
 * de los dos.
 */
#[Layout('layouts.app')]
#[Title('Identidad institucional')]
class InstitutionSettingsForm extends Component
{
    use WithFileUploads;

    public string $name = '';

    public $logoLogin = null;

    public $logoMark = null;

    public function mount(): void
    {
        $settings = InstitutionSettings::current();

        Gate::authorize('view', $settings);

        $this->name = $settings->name;
    }

    public function save(): void
    {
        $settings = InstitutionSettings::current();

        Gate::authorize('update', $settings);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'logoLogin' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'logoMark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'updated_by' => auth()->id(),
        ];

        if ($this->logoLogin) {
            if ($settings->logo_login_path) {
                Storage::disk('institution')->delete($settings->logo_login_path);
            }

            $attributes['logo_login_path'] = $this->logoLogin->store('', 'institution');
        }

        if ($this->logoMark) {
            if ($settings->logo_mark_path) {
                Storage::disk('institution')->delete($settings->logo_mark_path);
            }

            $attributes['logo_mark_path'] = $this->logoMark->store('', 'institution');
        }

        $settings->update($attributes);

        $this->reset('logoLogin', 'logoMark');

        // Sin scope a un componente específico (no ->to(...)): el sidebar
        // vive en App\Livewire\InstitutionBrandHeader, una instancia
        // totalmente aparte de este formulario — necesita que el evento
        // llegue a cualquier componente de la página que lo escuche, para
        // reflejar el cambio al instante sin recargar.
        $this->dispatch('institution-settings-updated');

        session()->flash('status', 'Identidad institucional actualizada correctamente.');
    }

    public function resetToDefaults(): void
    {
        $settings = InstitutionSettings::current();

        Gate::authorize('update', $settings);

        $settings->resetToDefaults();

        $this->name = $settings->name;
        $this->reset('logoLogin', 'logoMark');

        $this->dispatch('institution-settings-updated');

        session()->flash('status', 'Se restableció el nombre y los logos originales del sistema.');
    }

    /**
     * Un archivo recién seleccionado (todavía sin guardar) tiene prioridad
     * sobre el ya configurado — isPreviewable() antes de temporaryUrl():
     * un archivo que la validación va a rechazar de todas formas (ej. un
     * .pdf renombrado) no es previsualizable para Livewire, y
     * temporaryUrl() lanzaría una excepción solo por intentar mostrar la
     * vista previa en vivo, antes incluso de que save() valide nada.
     */
    public function getPreviewLoginLogoUrlProperty(): ?string
    {
        if ($this->logoLogin && $this->logoLogin->isPreviewable()) {
            return $this->logoLogin->temporaryUrl();
        }

        return InstitutionSettings::current()->loginLogoUrl();
    }

    public function getPreviewMarkLogoUrlProperty(): ?string
    {
        if ($this->logoMark && $this->logoMark->isPreviewable()) {
            return $this->logoMark->temporaryUrl();
        }

        return InstitutionSettings::current()->markLogoUrl();
    }

    public function render()
    {
        return view('livewire.admin.institution-settings-form', [
            'settings' => InstitutionSettings::current(),
        ]);
    }
}
