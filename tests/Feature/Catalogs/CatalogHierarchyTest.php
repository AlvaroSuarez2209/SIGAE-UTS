<?php

namespace Tests\Feature\Catalogs;

use App\Enums\RoleName;
use App\Livewire\Catalogs\CatalogHierarchy;
use App\Models\Activity;
use App\Models\Component;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prioridad 4: vista jerárquica Componente > Subcomponente > Actividad,
 * de solo lectura, nueva por completo (no existía nada parecido).
 */
class CatalogHierarchyTest extends TestCase
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

    public function test_teacher_cannot_access_the_hierarchy_view(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/catalogs/hierarchy')->assertForbidden();
    }

    public function test_reflects_the_real_structure_with_activities_nested_under_their_subcomponent(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $component = Component::factory()->create(['name' => 'Docencia']);
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos OACA']);
        $nestedActivity = Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponent->id,
            'name' => 'Clases teóricas',
        ]);
        $directActivity = Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => null,
            'name' => 'Tutorías',
        ]);

        // Un componente sin relación con el anterior nunca debe mezclarse
        // en la rama del primero.
        $otherComponent = Component::factory()->create(['name' => 'Investigación']);

        $response = Livewire::actingAs($admin)->test(CatalogHierarchy::class);

        $response->assertSee('Docencia');
        $response->assertSee('Procesos OACA');
        $response->assertSee('Clases teóricas');
        $response->assertSee('Tutorías');
        $response->assertSee('Investigación');

        // La actividad anidada aparece bajo su subcomponente real, no
        // suelta como si no tuviera uno.
        $html = $response->html();
        $subcomponentPos = strpos($html, 'Procesos OACA');
        $nestedActivityPos = strpos($html, 'Clases teóricas');
        $directActivityPos = strpos($html, 'Tutorías');
        $otherComponentPos = strpos($html, 'Investigación');

        $this->assertLessThan($nestedActivityPos, $subcomponentPos, 'La actividad anidada debe aparecer después de su subcomponente en el HTML.');
        $this->assertLessThan($otherComponentPos, $directActivityPos, 'Todo el contenido del primer componente debe aparecer antes del segundo componente.');
    }

    public function test_shows_empty_state_when_a_component_has_no_subcomponents_or_activities(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Component::factory()->create(['name' => 'Componente Vacío']);

        Livewire::actingAs($admin)
            ->test(CatalogHierarchy::class)
            ->assertSee('Componente Vacío')
            ->assertSee('Sin subcomponentes ni actividades registradas.');
    }

    /**
     * Ajuste de diseño: la insignia de estado solo debe aparecer en
     * registros Inactivos — un registro Activo (el caso normal) no lleva
     * ninguna, para que lo inactivo (la excepción) resalte de verdad en
     * vez de perderse entre decenas de pastillas verdes idénticas.
     */
    public function test_the_status_badge_only_appears_for_inactive_records_at_every_level(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        // Nombres neutros a propósito (sin la palabra "Activo"/"Inactivo"
        // dentro del nombre mismo) — de lo contrario el conteo de
        // ocurrencias de la insignia se confundiría con el propio texto
        // del nombre del registro.
        $component = Component::factory()->create(['name' => 'Docencia', 'is_active' => true]);
        $inactiveComponent = Component::factory()->create(['name' => 'Investigación', 'is_active' => false]);

        $subcomponent = Subcomponent::factory()->create([
            'component_id' => $component->id,
            'name' => 'Procesos OACA',
            'is_active' => true,
        ]);
        $inactiveSubcomponent = Subcomponent::factory()->create([
            'component_id' => $component->id,
            'name' => 'Procesos ODA',
            'is_active' => false,
        ]);

        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponent->id,
            'name' => 'Clases teóricas',
            'is_active' => true,
        ]);
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponent->id,
            'name' => 'Tutorías',
            'is_active' => false,
        ]);

        $html = Livewire::actingAs($admin)->test(CatalogHierarchy::class)->html();

        // Las clases de <x-active-badge> son un marcador único e inequívoco
        // de cada variante (activo/inactivo), sin depender de espacios
        // exactos alrededor del texto — 3 registros inactivos (componente,
        // subcomponente y actividad) deben producir 3 insignias "Inactivo"
        // y ninguna "Activo" (los 3 registros activos no llevan insignia).
        $this->assertSame(3, substr_count($html, 'bg-surface-muted text-text-secondary'));
        $this->assertSame(0, substr_count($html, 'bg-status-success-subtle text-status-success'));

        $this->assertStringContainsString('Docencia', $html);
        $this->assertStringContainsString('Investigación', $html);
        $this->assertStringContainsString('Procesos OACA', $html);
        $this->assertStringContainsString('Procesos ODA', $html);
        $this->assertStringContainsString('Clases teóricas', $html);
        $this->assertStringContainsString('Tutorías', $html);
    }
}
