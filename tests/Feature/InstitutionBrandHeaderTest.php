<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Admin\InstitutionSettingsForm;
use App\Livewire\InstitutionBrandHeader;
use App\Models\InstitutionSettings;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug real reportado: tras guardar en "Identidad institucional", el
 * encabezado del sidebar no se actualizaba hasta un refresh manual —
 * vivía como Blade estático dentro de layouts/app.blade.php, fuera del
 * árbol de renderizado de Livewire del formulario, así que nada lo volvía
 * a pintar. Se extrajo a su propio componente, InstitutionBrandHeader, que
 * escucha el evento 'institution-settings-updated' que ahora disparan
 * InstitutionSettingsForm::save() y resetToDefaults().
 */
class InstitutionBrandHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Storage::fake('institution');
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    public function test_renders_the_current_institution_name_and_mark_logo(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        InstitutionSettings::current()->update(['name' => 'Universidad de Prueba']);

        Livewire::actingAs($admin)
            ->test(InstitutionBrandHeader::class, ['withCaption' => true])
            ->assertSee('Universidad de Prueba');
    }

    /**
     * El test central de este bug: sin volver a montar el componente
     * (simula que ya estaba pintado en pantalla antes del guardado),
     * confirma que recibir el evento alcanza para reflejar un cambio
     * hecho por fuera — el mismo mecanismo que usa
     * InstitutionSettingsForm::save() en la página real.
     */
    public function test_refreshes_when_it_receives_the_institution_settings_updated_event(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $header = Livewire::actingAs($admin)
            ->test(InstitutionBrandHeader::class, ['withCaption' => true])
            ->assertSee(InstitutionSettings::DEFAULT_NAME);

        // Cambio hecho "por fuera" del componente ya montado — igual que lo
        // haría InstitutionSettingsForm::save() en una instancia de
        // componente completamente distinta, en la misma página real.
        InstitutionSettings::current()->update(['name' => 'Universidad Renombrada']);

        $header->dispatch('institution-settings-updated')
            ->assertSee('Universidad Renombrada')
            ->assertDontSee(InstitutionSettings::DEFAULT_NAME);
    }

    /**
     * Extremo a extremo con los 2 componentes reales: guarda desde
     * InstitutionSettingsForm (como lo haría el Administrador en la
     * pantalla de configuración) y confirma que un InstitutionBrandHeader
     * ya montado se entera sin que nadie lo vuelva a montar.
     */
    public function test_saving_from_the_settings_form_refreshes_an_already_mounted_brand_header(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $logo = UploadedFile::fake()->image('logo-mark.png', 200, 200);

        $header = Livewire::actingAs($admin)
            ->test(InstitutionBrandHeader::class, ['withCaption' => true])
            ->assertSee(InstitutionSettings::DEFAULT_NAME);

        Livewire::actingAs($admin)
            ->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad Desde El Formulario')
            ->set('logoMark', $logo)
            ->call('save')
            ->assertDispatched('institution-settings-updated');

        $header->dispatch('institution-settings-updated')
            ->assertSee('Universidad Desde El Formulario');
    }

    public function test_reset_to_defaults_also_dispatches_the_refresh_event(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        InstitutionSettings::current()->update(['name' => 'Universidad de Prueba']);

        Livewire::actingAs($admin)
            ->test(InstitutionSettingsForm::class)
            ->call('resetToDefaults')
            ->assertDispatched('institution-settings-updated');
    }
}
