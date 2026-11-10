<?php

namespace Tests\Feature\Catalogs;

use App\Enums\RoleName;
use App\Livewire\Periods\PeriodIndex;
use App\Models\AcademicPeriod;
use App\Models\Leadership;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Catalog\CatalogDeletionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Punto 2 + su ajuste: un periodo académico solo se puede borrar sin
 * ningún dependiente — igual que los otros 6 tipos — más una regla
 * extra: ciertos códigos (config('catalogs.protected_academic_period_codes'),
 * por defecto incluye PRUEBA-COORD-20261026, el periodo de pruebas de la
 * directora) nunca se pueden borrar, tengan o no dependientes. El botón
 * nunca está deshabilitado; la confirmación exige escribir el código
 * del periodo, no solo un clic.
 */
class AcademicPeriodDeletionTest extends TestCase
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

    private function coordination(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Coordination->value)->first());

        return $user;
    }

    public function test_the_delete_button_is_never_disabled_even_for_the_protected_period(): void
    {
        config(['catalogs.protected_academic_period_codes' => ['PRUEBA-COORD-20261026']]);
        $period = AcademicPeriod::factory()->create(['name' => 'PRUEBA-COORD-20261026']);

        $html = Livewire::actingAs($this->admin())->test(PeriodIndex::class)->html();

        $this->assertStringContainsString('btn-delete', $html);
        $this->assertStringContainsString('openDeleteConfirmation('.$period->id.')', $html);
        $this->assertStringNotContainsString('cursor-not-allowed', $html);
    }

    public function test_opening_the_modal_without_dependents_shows_the_confirmation_variant(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2027-1']);

        Livewire::actingAs($this->admin())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->assertSet('deletionBlockers', [])
            ->assertSet('deletionProtectedMessage', null)
            ->assertSeeHtml('Eliminar definitivamente')
            ->assertDontSeeHtml('No se puede eliminar');
    }

    public function test_administrator_can_delete_a_period_without_dependents_by_typing_its_code(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2027-1']);

        Livewire::actingAs($this->admin())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->call('deletePeriod', '2027-1');

        $this->assertDatabaseMissing('academic_periods', ['id' => $period->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => AcademicPeriod::class,
            'auditable_id' => $period->id,
        ]);
    }

    public function test_cannot_delete_a_period_when_the_typed_code_does_not_match(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2027-1']);

        Livewire::actingAs($this->admin())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->call('deletePeriod', 'codigo-incorrecto')
            ->assertSet('deletionError', fn ($message) => str_contains($message, 'no coincide'));

        $this->assertDatabaseHas('academic_periods', ['id' => $period->id]);
    }

    public function test_opening_the_modal_with_dependents_shows_the_blocked_variant_with_quantities(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2027-1']);
        TeacherAssignment::factory()->create(['academic_period_id' => $period->id]);

        Livewire::actingAs($this->admin())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->assertSet('deletionBlockers', ['1 asignación docente'])
            ->assertSeeHtml('No se puede eliminar')
            ->assertDontSeeHtml('Eliminar definitivamente');

        $this->assertDatabaseHas('academic_periods', ['id' => $period->id]);
    }

    public function test_cannot_delete_a_period_with_a_leadership(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2027-1']);
        Leadership::factory()->create(['academic_period_id' => $period->id]);

        Livewire::actingAs($this->admin())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->assertSet('deletionBlockers', ['1 liderazgo']);

        $this->assertDatabaseHas('academic_periods', ['id' => $period->id]);
    }

    public function test_the_protected_period_shows_its_own_message_even_without_any_dependents(): void
    {
        config(['catalogs.protected_academic_period_codes' => ['PRUEBA-COORD-20261026']]);
        $period = AcademicPeriod::factory()->create(['name' => 'PRUEBA-COORD-20261026']);

        Livewire::actingAs($this->admin())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->assertSet('deletionProtectedMessage', 'Este periodo está protegido y no puede eliminarse.')
            ->assertSeeHtml('protegido')
            ->assertDontSeeHtml('Eliminar definitivamente');

        $this->assertDatabaseHas('academic_periods', ['id' => $period->id]);
    }

    public function test_the_protected_period_is_rejected_by_the_service_directly_regardless_of_the_ui(): void
    {
        config(['catalogs.protected_academic_period_codes' => ['PRUEBA-COORD-20261026']]);
        $period = AcademicPeriod::factory()->create(['name' => 'PRUEBA-COORD-20261026']);

        $result = CatalogDeletionService::deleteAcademicPeriod($period);

        $this->assertFalse($result->deleted);
        $this->assertStringContainsString('protegido', $result->message);
        $this->assertDatabaseHas('academic_periods', ['id' => $period->id]);
    }

    public function test_coordination_does_not_see_the_delete_button_and_cannot_open_the_modal(): void
    {
        $period = AcademicPeriod::factory()->create(['name' => '2027-1']);

        $html = Livewire::actingAs($this->coordination())->test(PeriodIndex::class)->html();
        $this->assertStringNotContainsString('openDeleteConfirmation(', $html);

        Livewire::actingAs($this->coordination())
            ->test(PeriodIndex::class)
            ->call('openDeleteConfirmation', $period)
            ->assertForbidden();

        $this->assertDatabaseHas('academic_periods', ['id' => $period->id]);
    }
}
