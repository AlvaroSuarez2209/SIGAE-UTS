<?php

namespace Tests\Feature\Ui;

use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Livewire\Periods\PeriodIndex;
use App\Models\Role;
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
}
