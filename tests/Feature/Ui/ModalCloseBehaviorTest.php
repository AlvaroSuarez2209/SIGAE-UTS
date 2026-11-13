<?php

namespace Tests\Feature\Ui;

use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Livewire\Periods\PeriodIndex;
use App\Models\Component;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Los modales de formulario (catálogos, periodos) y el modal de
 * confirmación deben cerrarse solo con el botón de cerrar o con Escape —
 * nunca con un clic fuera, para no descartar en silencio un formulario a
 * medio llenar ni cancelar por accidente una acción destructiva. Ver
 * docs/manual-tecnico.md §5.8 y manual-diseno.md.
 */
class ModalCloseBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Administrator->value)->first());

        return $user;
    }

    public static function catalogComponents(): array
    {
        return [
            [ComponentIndex::class],
            [SubcomponentIndex::class],
            [ActivityIndex::class],
            [ProgramUnitIndex::class],
            [CrossCuttingCommitmentIndex::class],
            [PeriodIndex::class],
        ];
    }

    #[DataProvider('catalogComponents')]
    public function test_catalog_modal_has_no_click_outside_handler(string $component): void
    {
        $html = Livewire::actingAs($this->admin())
            ->test($component)
            ->call('openCreate')
            ->html();

        $this->assertStringNotContainsString('click.outside', $html);
        $this->assertStringContainsString('keydown.escape', $html);
        $this->assertStringContainsString('closeModal', $html);
    }

    public function test_confirm_modal_has_no_click_outside_handler(): void
    {
        $response = $this->actingAs($this->admin())->get('/catalogs/components');

        $response->assertOk();
        $this->assertStringNotContainsString('click.outside', $response->getContent());
        $this->assertStringContainsString('keydown.escape', $response->getContent());
    }

    /**
     * Evaluación de accesibilidad del punto 23: antes, Tab podía escapar
     * del modal hacia el fondo de la página. x-trap (plugin de foco de
     * Alpine, incluido en el bundle de Livewire) ahora atrapa el Tab
     * mientras "open" es true, y devuelve el foco al botón que abrió el
     * modal al cerrarse (Cancelar, Confirmar o Escape, los tres solo
     * cambian "open").
     */
    public function test_confirm_modal_traps_tab_focus_and_returns_it_to_the_trigger(): void
    {
        $response = $this->actingAs($this->admin())->get('/catalogs/components');

        $response->assertOk();
        $this->assertStringContainsString('x-trap="open"', $response->getContent());
        $this->assertStringContainsString('autofocus', $response->getContent());
    }

    /**
     * Ajuste al punto 2: el nuevo modal de borrado real
     * (<x-catalog-delete-modal>) sigue el mismo criterio que el resto —
     * nunca un clic fuera, ver docstring de esta clase.
     */
    public function test_catalog_delete_modal_has_no_click_outside_handler(): void
    {
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id]);

        $html = Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->html();

        $this->assertStringNotContainsString('click.outside', $html);
        $this->assertStringContainsString('keydown.escape', $html);
        $this->assertStringContainsString('closeDeleteConfirmation', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
    }

    /**
     * Evaluación de accesibilidad del punto 23: mismo x-trap que
     * <x-confirm-modal>, pero con .noreturn.noautofocus — este modal se
     * remonta por completo con cada apertura/cierre de Livewire, así que
     * el foco inicial y el retorno al disparador siguen a cargo del
     * mecanismo ya existente (x-init de abajo y
     * window.__sigaeDeleteTrigger en cada botón, ya probados arriba);
     * x-trap aquí solo agrega la contención del Tab.
     */
    public function test_catalog_delete_modal_traps_tab_focus(): void
    {
        $component = Component::factory()->create();

        $html = Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->html();

        $this->assertStringContainsString('x-trap.noreturn.noautofocus="true"', $html);
    }
}
