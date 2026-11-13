<?php

namespace Tests\Feature\Catalogs;

use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    public function test_leader_cannot_manage_catalogs(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($leader)->get('/catalogs/components')->assertForbidden();
    }

    public function test_coordination_can_create_a_component(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(ComponentIndex::class)
            ->set('name', 'Bienestar institucional')
            ->call('save');

        $this->assertDatabaseHas('components', ['name' => 'Bienestar institucional', 'is_active' => true]);
    }

    /**
     * Punto 6 (ampliación), corrección tras pruebas manuales: el listado
     * principal de cada catálogo vuelve a ser alfabético (antes era "más
     * reciente primero", ver OrdersNewestFirst ya eliminado) e insensible
     * a tildes.
     */
    public function test_component_index_lists_alphabetically_without_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Component::factory()->create(['name' => 'Zonas de bienestar']);
        Component::factory()->create(['name' => 'Áreas académicas']);
        Component::factory()->create(['name' => 'Bienestar institucional']);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->assertViewHas('components', fn ($components) => $components->pluck('name')->all() === [
                'Áreas académicas',
                'Bienestar institucional',
                'Zonas de bienestar',
            ]);
    }

    public function test_a_newly_created_component_appears_in_its_alphabetical_position_not_at_the_top(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Component::factory()->create(['name' => 'Áreas académicas']);
        Component::factory()->create(['name' => 'Zonas de bienestar']);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->set('name', 'Bienestar institucional')
            ->call('save');

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->assertViewHas('components', fn ($components) => $components->pluck('name')->all() === [
                'Áreas académicas',
                'Bienestar institucional',
                'Zonas de bienestar',
            ]);
    }

    public function test_deactivating_a_component_does_not_delete_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('toggleActive', $component);

        $this->assertDatabaseHas('components', ['id' => $component->id, 'is_active' => false]);
    }

    public function test_subcomponent_requires_a_valid_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('name', 'Procesos OACA')
            ->set('component_id', null)
            ->call('save')
            ->assertHasErrors('component_id');
    }

    public function test_activity_subcomponent_must_belong_to_the_chosen_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $componentA = Component::factory()->create();
        $componentB = Component::factory()->create();
        $subcomponentOfB = Subcomponent::factory()->create(['component_id' => $componentB->id]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Actividad cruzada')
            ->set('component_id', $componentA->id)
            ->set('subcomponent_id', $subcomponentOfB->id)
            ->call('save')
            ->assertHasErrors('subcomponent_id');
    }

    public function test_activity_can_be_created_without_a_subcomponent(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Clases teóricas')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activities', [
            'name' => 'Clases teóricas',
            'component_id' => $component->id,
            'subcomponent_id' => null,
        ]);
    }

    /**
     * Puntos 3 y 4 de la revisión de la directora: descripción opcional e
     * indicador de obligatoriedad (default true, el checkbox nace marcado).
     */
    public function test_activity_can_be_created_with_a_description_and_mandatory_flag(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Acompañamiento a egresados')
            ->set('component_id', $component->id)
            ->set('description', 'Seguimiento semestral a egresados del programa.')
            ->set('is_mandatory', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activities', [
            'name' => 'Acompañamiento a egresados',
            'description' => 'Seguimiento semestral a egresados del programa.',
            'is_mandatory' => false,
        ]);
    }

    public function test_activity_can_be_created_without_a_description(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Clases prácticas')
            ->set('component_id', $component->id)
            ->set('description', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activities', [
            'name' => 'Clases prácticas',
            'description' => null,
            'is_mandatory' => true,
        ]);
    }

    /**
     * La columna "Descripción" del listado recorta a 2 líneas
     * (line-clamp-2) pero conserva el texto completo en el atributo
     * title, para verlo al pasar el cursor.
     */
    public function test_activity_list_shows_the_full_description_in_the_title_attribute(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->create([
            'description' => 'Seguimiento semestral a egresados del programa, con encuestas y entrevistas de seguimiento.',
        ]);

        $html = Livewire::actingAs($admin)->test(ActivityIndex::class)->html();

        $this->assertStringContainsString(
            'title="Seguimiento semestral a egresados del programa, con encuestas y entrevistas de seguimiento."',
            $html
        );
        $this->assertStringContainsString('line-clamp-2', $html);
    }

    public function test_activity_list_shows_a_dash_and_no_title_when_there_is_no_description(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->create(['name' => 'Sin descripción', 'description' => null]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertOk()
            ->assertSee('—');
    }

    /**
     * Corrección puntual (revisión de la directora): el select de
     * "Componente" del formulario de actividad no filtraba por activos.
     */
    public function test_activity_form_component_select_only_offers_active_components(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $active = Component::factory()->create(['is_active' => true]);
        $inactive = Component::factory()->create(['is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertViewHas('components', function ($components) use ($active, $inactive) {
                return $components->contains('id', $active->id)
                    && ! $components->contains('id', $inactive->id);
            });
    }

    /**
     * Mismo componente para las 3, sin subcomponente: dentro de ese
     * único grupo, el orden jerárquico (ver test de hierarchy más
     * abajo) se reduce al orden alfabético por nombre de actividad.
     */
    public function test_activity_index_lists_alphabetically_without_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        Activity::factory()->create(['component_id' => $component->id, 'name' => 'Zonas de bienestar']);
        Activity::factory()->create(['component_id' => $component->id, 'name' => 'Áreas académicas']);
        Activity::factory()->create(['component_id' => $component->id, 'name' => 'Bienestar institucional']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertViewHas('activities', fn ($activities) => $activities->pluck('name')->all() === [
                'Áreas académicas',
                'Bienestar institucional',
                'Zonas de bienestar',
            ]);
    }

    /**
     * Corrección posterior: ordena actividades por componente, luego
     * subcomponente, luego nombre de actividad — todo insensible a
     * tildes/mayúsculas. Las actividades sin subcomponente van al
     * inicio de su componente (se tratan como cadena vacía).
     */
    public function test_activity_index_lists_by_component_then_subcomponent_then_activity_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $componentZ = Component::factory()->create(['name' => 'Zonas de bienestar']);
        $componentA = Component::factory()->create(['name' => 'Ángel']);

        $subB = Subcomponent::factory()->create(['component_id' => $componentA->id, 'name' => 'Beta']);
        $subA = Subcomponent::factory()->create(['component_id' => $componentA->id, 'name' => 'Álvaro']);

        Activity::factory()->create(['component_id' => $componentZ->id, 'subcomponent_id' => null, 'name' => 'Solitaria']);
        Activity::factory()->create(['component_id' => $componentA->id, 'subcomponent_id' => null, 'name' => 'Zeta sin subcomponente']);
        Activity::factory()->create(['component_id' => $componentA->id, 'subcomponent_id' => $subB->id, 'name' => 'Última']);
        Activity::factory()->create(['component_id' => $componentA->id, 'subcomponent_id' => $subA->id, 'name' => 'Primera']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertViewHas('activities', fn ($activities) => $activities->pluck('name')->all() === [
                // Componente "Ángel" antes que "Zonas de bienestar" (A antes de Z).
                'Zeta sin subcomponente', // sin subcomponente: va primero dentro de "Ángel".
                'Primera', // subcomponente "Álvaro".
                'Última', // subcomponente "Beta".
                'Solitaria', // único de "Zonas de bienestar".
            ]);
    }

    /**
     * ActivityIndex pagina (a diferencia de los otros 4 catálogos, que
     * usan ->get()) — el desempate por id importa para que la paginación
     * no repita ni pierda filas cuando varias actividades comparten el
     * mismo nombre normalizado.
     */
    public function test_activity_index_pagination_is_stable_with_duplicate_normalized_names(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activities = Activity::factory()->count(30)->create(['name' => 'Actividad repetida']);

        $page1 = Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('gotoPage', 1)
            ->viewData('activities');

        $page2 = Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('gotoPage', 2)
            ->viewData('activities');

        $seenIds = $page1->pluck('id')->merge($page2->pluck('id'));

        $this->assertSame($activities->pluck('id')->sort()->values()->all(), $seenIds->sort()->values()->all());
        $this->assertSame($seenIds->unique()->count(), $seenIds->count());
    }

    public function test_deactivating_an_activity_preserves_historical_reference(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('toggleActive', $activity);

        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'is_active' => false]);
    }

    // Regresión: un intento de creación fallido no debe dejar errores de
    // validación visibles al abrir "Editar" sobre un registro válido —
    // ver [[project-livewire4-gotchas]].

    public function test_component_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['name' => 'Docencia']);

        $test = Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('openCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name')
            ->call('closeModal')
            ->assertHasNoErrors();

        $test->call('openEdit', $component)
            ->assertHasNoErrors()
            ->assertSet('name', 'Docencia');
    }

    public function test_subcomponent_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos OACA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name', 'component_id'])
            ->call('openEdit', $subcomponent)
            ->assertHasNoErrors()
            ->assertSet('name', 'Procesos OACA');
    }

    public function test_activity_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['name' => 'Clases teóricas']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name', 'component_id'])
            ->call('openEdit', $activity)
            ->assertHasNoErrors()
            ->assertSet('name', 'Clases teóricas');
    }

    public function test_program_unit_index_lists_alphabetically_without_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Zonas de bienestar']);
        ProgramUnit::factory()->create(['name' => 'Áreas académicas']);
        ProgramUnit::factory()->create(['name' => 'Bienestar institucional']);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->assertViewHas('programUnits', fn ($programUnits) => $programUnits->pluck('name')->all() === [
                'Áreas académicas',
                'Bienestar institucional',
                'Zonas de bienestar',
            ]);
    }

    public function test_program_unit_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = ProgramUnit::factory()->create(['name' => 'Facultad de Ciencias Naturales']);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors('name')
            ->call('openEdit', $programUnit)
            ->assertHasNoErrors()
            ->assertSet('name', 'Facultad de Ciencias Naturales');
    }

    public function test_cross_cutting_commitment_index_lists_alphabetically_without_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        CrossCuttingCommitment::factory()->create(['name' => 'Zonas de bienestar']);
        CrossCuttingCommitment::factory()->create(['name' => 'Áreas académicas']);
        CrossCuttingCommitment::factory()->create(['name' => 'Bienestar institucional']);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->assertViewHas('commitments', fn ($commitments) => $commitments->pluck('name')->all() === [
                'Áreas académicas',
                'Bienestar institucional',
                'Zonas de bienestar',
            ]);
    }

    public function test_cross_cutting_commitment_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $commitment = CrossCuttingCommitment::factory()->create(['name' => 'Bienestar y desarrollo humano']);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors('name')
            ->call('openEdit', $commitment)
            ->assertHasNoErrors()
            ->assertSet('name', 'Bienestar y desarrollo humano');
    }

    public function test_component_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Component::factory()->create(['name' => 'Inducción']);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->set('name', 'INDUCCION')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_editing_a_component_does_not_flag_its_own_unchanged_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['name' => 'Inducción']);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('openEdit', $component)
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_program_unit_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->set('name', 'ingenieria de sistemas')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_cross_cutting_commitment_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        CrossCuttingCommitment::factory()->create(['name' => 'Bienestar y desarrollo humano']);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->set('name', 'BIENESTAR Y DESARROLLO HUMANO')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_subcomponent_name_is_rejected_regardless_of_case_or_accents_within_the_same_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos OACA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('name', 'procesos oaca')
            ->set('component_id', $component->id)
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_subcomponent_name_is_allowed_to_repeat_under_a_different_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $componentA = Component::factory()->create();
        $componentB = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $componentA->id, 'name' => 'Procesos OACA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('name', 'Procesos OACA')
            ->set('component_id', $componentB->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_activity_name_is_rejected_regardless_of_case_or_accents_within_the_same_scope(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id]);
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponent->id,
            'name' => 'Inducción docente',
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'INDUCCION DOCENTE')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', $subcomponent->id)
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_activity_name_is_allowed_to_repeat_under_a_different_subcomponent(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $subcomponentA = Subcomponent::factory()->create(['component_id' => $component->id]);
        $subcomponentB = Subcomponent::factory()->create(['component_id' => $component->id]);
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponentA->id,
            'name' => 'Inducción docente',
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Inducción docente')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', $subcomponentB->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_activity_name_without_a_subcomponent_is_rejected_regardless_of_case_when_repeated(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => null,
            'name' => 'Clases teóricas',
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'CLASES TEORICAS')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', null)
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_closing_the_component_modal_resets_the_form(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('openCreate')
            ->set('name', 'Texto sin guardar')
            ->call('closeModal')
            ->assertSet('name', '')
            ->assertSet('showModal', false);
    }

    public function test_activity_search_filters_by_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->create(['name' => 'Clases teóricas']);
        Activity::factory()->create(['name' => 'Dirección de trabajos de grado']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('search', 'teóricas')
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1
                && $activities->first()->name === 'Clases teóricas');
    }

    public function test_activity_search_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->create(['name' => 'Clases teóricas']);
        Activity::factory()->create(['name' => 'Dirección de trabajos de grado']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('search', 'teoricas')
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1
                && $activities->first()->name === 'Clases teóricas');
    }

    public function test_activity_index_paginates_at_25_per_page(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->count(26)->create();

        $test = Livewire::actingAs($admin)->test(ActivityIndex::class);

        $test->assertViewHas('activities', function ($activities) {
            return $activities->perPage() === 25
                && $activities->count() === 25
                && $activities->total() === 26
                && $activities->hasMorePages();
        });

        $test->assertSee('Siguiente');
    }

    public function test_subcomponent_index_lists_alphabetically_without_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Zonas de bienestar']);
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Áreas académicas']);
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Bienestar institucional']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->assertViewHas('subcomponents', fn ($subcomponents) => $subcomponents->pluck('name')->all() === [
                'Áreas académicas',
                'Bienestar institucional',
                'Zonas de bienestar',
            ]);
    }

    public function test_subcomponent_search_filters_by_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Subcomponent::factory()->create(['name' => 'Procesos OACA']);
        Subcomponent::factory()->create(['name' => 'Procesos ODA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('search', 'OACA')
            ->assertViewHas('subcomponents', fn ($subcomponents) => $subcomponents->count() === 1
                && $subcomponents->first()->name === 'Procesos OACA');
    }

    public function test_subcomponent_search_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Subcomponent::factory()->create(['name' => 'Inducción docente']);
        Subcomponent::factory()->create(['name' => 'Procesos ODA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('search', 'induccion')
            ->assertViewHas('subcomponents', fn ($subcomponents) => $subcomponents->count() === 1
                && $subcomponents->first()->name === 'Inducción docente');
    }

    /**
     * Bloque de ajustes de interfaz, punto 5, punto 3: crear/editar/
     * activar/desactivar un catálogo no mostraba ningún mensaje — solo se
     * cerraba el modal y la lista se actualizaba. Estas 5 pantallas no
     * redirigen (el modal cierra en la misma página): el flash se setea y
     * el componente se vuelve a renderizar dentro de la MISMA petición, así
     * que <x-flash-message /> (incluido directamente en la vista de cada
     * catálogo) ya lo muestra en el HTML que devuelve ese mismo ->call().
     */
    public function test_creating_updating_and_toggling_a_component_shows_a_success_message(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)->test(ComponentIndex::class)
            ->set('name', 'Bienestar institucional')->call('save')
            ->assertSee('Componente creado correctamente.');

        Livewire::actingAs($admin)->test(ComponentIndex::class)
            ->call('openEdit', $component)->set('name', 'Nuevo nombre')->call('save')
            ->assertSee('Componente actualizado correctamente.');

        Livewire::actingAs($admin)->test(ComponentIndex::class)
            ->call('toggleActive', $component)
            ->assertSee('Componente desactivado.');
    }

    public function test_creating_updating_and_toggling_a_subcomponent_shows_a_success_message(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $parent = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $parent->id, 'is_active' => true]);

        Livewire::actingAs($admin)->test(SubcomponentIndex::class)
            ->set('name', 'Procesos OACA')->set('component_id', $parent->id)->call('save')
            ->assertSee('Subcomponente creado correctamente.');

        Livewire::actingAs($admin)->test(SubcomponentIndex::class)
            ->call('openEdit', $subcomponent)->set('name', 'Nuevo nombre')->call('save')
            ->assertSee('Subcomponente actualizado correctamente.');

        Livewire::actingAs($admin)->test(SubcomponentIndex::class)
            ->call('toggleActive', $subcomponent)
            ->assertSee('Subcomponente desactivado.');
    }

    public function test_creating_updating_and_toggling_an_activity_shows_a_success_message(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $parent = Component::factory()->create();
        $activity = Activity::factory()->create(['component_id' => $parent->id, 'is_active' => true]);

        Livewire::actingAs($admin)->test(ActivityIndex::class)
            ->set('name', 'Docencia directa')->set('component_id', $parent->id)->call('save')
            ->assertSee('Actividad creada correctamente.');

        Livewire::actingAs($admin)->test(ActivityIndex::class)
            ->call('openEdit', $activity)->set('name', 'Nuevo nombre')->call('save')
            ->assertSee('Actividad actualizada correctamente.');

        Livewire::actingAs($admin)->test(ActivityIndex::class)
            ->call('toggleActive', $activity)
            ->assertSee('Actividad desactivada.');
    }

    public function test_creating_updating_and_toggling_a_program_unit_shows_a_success_message(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = ProgramUnit::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)->test(ProgramUnitIndex::class)
            ->set('name', 'Ingeniería de Sistemas')->call('save')
            ->assertSee('Programa creado correctamente.');

        Livewire::actingAs($admin)->test(ProgramUnitIndex::class)
            ->call('openEdit', $programUnit)->set('name', 'Nuevo nombre')->call('save')
            ->assertSee('Programa actualizado correctamente.');

        Livewire::actingAs($admin)->test(ProgramUnitIndex::class)
            ->call('toggleActive', $programUnit)
            ->assertSee('Programa desactivado.');
    }

    public function test_creating_updating_and_toggling_a_cross_cutting_commitment_shows_a_success_message(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $commitment = CrossCuttingCommitment::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)->test(CrossCuttingCommitmentIndex::class)
            ->set('name', 'Bilingüismo')->call('save')
            ->assertSee('Compromiso transversal creado correctamente.');

        Livewire::actingAs($admin)->test(CrossCuttingCommitmentIndex::class)
            ->call('openEdit', $commitment)->set('name', 'Nuevo nombre')->call('save')
            ->assertSee('Compromiso transversal actualizado correctamente.');

        Livewire::actingAs($admin)->test(CrossCuttingCommitmentIndex::class)
            ->call('toggleActive', $commitment)
            ->assertSee('Compromiso transversal desactivado.');
    }

    public static function catalogComponents(): array
    {
        return [
            [ComponentIndex::class],
            [SubcomponentIndex::class],
            [ActivityIndex::class],
            [ProgramUnitIndex::class],
            [CrossCuttingCommitmentIndex::class],
        ];
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: el botón "Guardar" del modal
     * de los 5 catálogos (mismo scaffold generado) no tenía ningún estado
     * de carga — mismo patrón ya usado en
     * Login/InstitutionSettingsForm/TeacherImportWizard.
     */
    #[DataProvider('catalogComponents')]
    public function test_the_save_button_disables_itself_and_shows_a_loading_state(string $component): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test($component)
            ->call('openCreate')
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="save"', false)
            ->assertSee('Guardando...');
    }

    /**
     * Cuadro de seguimiento, punto 23: una búsqueda sin coincidencias
     * mostraba el mismo texto que "no hay nada registrado en absoluto"
     * ("No hay programas registrados", etc.) — confuso cuando sí hay
     * filas, solo que ninguna coincide con la búsqueda. Los 5 catálogos
     * ahora distinguen el caso según filled($search).
     */
    #[DataProvider('catalogComponents')]
    public function test_a_search_with_no_matches_shows_a_search_specific_empty_message(string $component): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test($component)
            ->set('search', 'ninguna-coincidencia-posible-xyz')
            ->assertSee('Sin resultados para esta búsqueda');
    }
}
