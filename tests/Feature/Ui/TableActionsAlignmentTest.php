<?php

namespace Tests\Feature\Ui;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Livewire\Deliverables\TemplateIndex;
use App\Livewire\Periods\PeriodIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\DeliverableTemplate;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ajuste de alineación: la celda "Acciones" de los 7 listados de
 * catálogo usa <x-table-actions> (fila flex, sin salto de línea) y
 * <x-table-toggle-slot> (ancho mínimo fijo para el botón que cambia de
 * texto según el estado) para que "Editar" y "Eliminar" queden siempre
 * en la misma posición horizontal, sea la fila activa o inactiva. Esta
 * prueba no mide píxeles — confirma que el marcado compartido (mismas
 * clases, mismo ancho mínimo) se repite idéntico en cada fila, sin
 * importar su estado.
 */
class TableActionsAlignmentTest extends TestCase
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

    private const WRAPPER_CLASSES = 'inline-flex items-center justify-end gap-4 whitespace-nowrap';

    public function test_component_active_and_inactive_rows_share_the_same_actions_structure(): void
    {
        Component::factory()->create(['is_active' => true]);
        Component::factory()->create(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test(ComponentIndex::class)->html();

        $this->assertSame(2, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(2, substr_count($html, 'style="min-width: 7rem"'));
    }

    public function test_subcomponent_active_and_inactive_rows_share_the_same_actions_structure(): void
    {
        Subcomponent::factory()->create(['is_active' => true]);
        Subcomponent::factory()->create(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test(SubcomponentIndex::class)->html();

        $this->assertSame(2, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(2, substr_count($html, 'style="min-width: 7rem"'));
    }

    public function test_activity_active_and_inactive_rows_share_the_same_actions_structure(): void
    {
        Activity::factory()->create(['is_active' => true]);
        Activity::factory()->create(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test(ActivityIndex::class)->html();

        $this->assertSame(2, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(2, substr_count($html, 'style="min-width: 7rem"'));
    }

    public function test_program_unit_active_and_inactive_rows_share_the_same_actions_structure(): void
    {
        ProgramUnit::factory()->create(['is_active' => true]);
        ProgramUnit::factory()->create(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test(ProgramUnitIndex::class)->html();

        $this->assertSame(2, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(2, substr_count($html, 'style="min-width: 7rem"'));
    }

    public function test_commitment_active_and_inactive_rows_share_the_same_actions_structure(): void
    {
        CrossCuttingCommitment::factory()->create(['is_active' => true]);
        CrossCuttingCommitment::factory()->create(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test(CrossCuttingCommitmentIndex::class)->html();

        $this->assertSame(2, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(2, substr_count($html, 'style="min-width: 7rem"'));
    }

    public function test_template_active_and_inactive_rows_share_the_same_actions_structure(): void
    {
        DeliverableTemplate::factory()->create(['is_active' => true]);
        DeliverableTemplate::factory()->create(['is_active' => false]);

        $html = Livewire::actingAs($this->admin())->test(TemplateIndex::class)->html();

        $this->assertSame(2, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(2, substr_count($html, 'style="min-width: 7rem"'));
    }

    /**
     * Periodos es el caso más exigente: según el estado puede mostrar 0
     * ("archived"), 1 ("planning"/"active") o 2 botones a la vez
     * ("closed": Archivar + Reabrir) en el slot central — las cuatro
     * filas deben reservar exactamente el mismo ancho (10rem) de todas
     * formas, para que "Editar" y "Eliminar" no se muevan entre ellas.
     */
    public function test_period_rows_of_every_status_share_the_same_actions_structure(): void
    {
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);

        $html = Livewire::actingAs($this->admin())->test(PeriodIndex::class)->html();

        $this->assertSame(4, substr_count($html, self::WRAPPER_CLASSES));
        $this->assertSame(4, substr_count($html, 'style="min-width: 10rem"'));
    }
}
