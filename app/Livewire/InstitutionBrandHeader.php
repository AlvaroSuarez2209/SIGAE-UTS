<?php

namespace App\Livewire;

use App\Models\InstitutionSettings;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Logo + nombre institucional del sidebar/encabezados de la app
 * autenticada — extraído de layouts/app.blade.php, donde antes vivía
 * inline como Blade estático: se pintaba una sola vez al cargar la
 * página, así que un guardado exitoso en "Identidad institucional" nunca
 * se reflejaba ahí hasta un refresh manual (el bug reportado). Al ser su
 * propio componente Livewire, puede escuchar el evento que dispara
 * InstitutionSettingsForm::save()/resetToDefaults() y volver a pintarse
 * solo, sin recargar la página.
 *
 * Las 3 apariciones del bloque logo+nombre (sidebar de escritorio, drawer
 * móvil, barra superior móvil) difieren un poco en tamaño/separación y en
 * si muestran el nombre institucional debajo — se preservan tal cual
 * estaban antes de esta extracción, vía props, no se unifican.
 */
class InstitutionBrandHeader extends Component
{
    public string $logoClass = 'h-9 w-9';

    public string $gap = 'gap-3';

    public bool $withCaption = false;

    public bool $clickable = true;

    /**
     * Sin cuerpo a propósito: render() ya vuelve a leer
     * InstitutionSettings::current() fresco en cada render — este método
     * solo existe para que el listener dispare el re-render.
     */
    #[On('institution-settings-updated')]
    public function refresh(): void {}

    public function render()
    {
        return view('livewire.institution-brand-header', [
            'institution' => InstitutionSettings::current(),
        ]);
    }
}
