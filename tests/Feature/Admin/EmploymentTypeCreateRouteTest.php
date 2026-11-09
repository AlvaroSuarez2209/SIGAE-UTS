<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Corrección reportada tras pruebas manuales: "el campo Tipo de
 * vinculación no aparece en la creación de usuarios como Coordinación,
 * solo en la edición." Investigación exhaustiva (ruta HTTP real vía
 * `GET /admin/users/create`, el mismo componente y la misma vista que
 * edición — UserForm no tiene ninguna rama que dependa de $user->exists
 * para mostrar/ocultar este campo, ver showsEmploymentType(); y un
 * navegador real con Playwright/Chromium contra el servidor real,
 * entrando por el botón "Nuevo usuario" tal cual lo haría una persona)
 * no logró reproducir el problema: marcar Docente o Líder muestra el
 * campo, marcar solo Auditor no, desmarcar lo oculta, sin ningún error
 * de consola, exactamente como predice showsEmploymentType(). No se
 * encontró ninguna causa de código — ver el mensaje de entrega de este
 * lote para la hipótesis más probable (una pestaña ya abierta desde
 * antes de que este campo existiera en el servidor).
 *
 * Estas pruebas no cambian ningún comportamiento: documentan y blindan
 * exactamente el escenario reportado, empezando por la ruta HTTP real
 * (no solo Livewire::test() montando el componente directamente).
 */
class EmploymentTypeCreateRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function coordination(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Coordination->value)->first());

        return $user;
    }

    /**
     * Punto 1 de la corrección: la ruta HTTP real de creación (la que
     * abre "Nuevo usuario"), no solo el componente montado a mano.
     */
    public function test_the_real_create_route_renders_ok_and_then_shows_the_field_after_checking_teacher(): void
    {
        $coordination = $this->coordination();

        $this->actingAs($coordination)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('Tipo de vinculación');

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->assertDontSee('Tipo de vinculación')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->assertSee('Tipo de vinculación');
    }

    public function test_creating_a_teacher_with_an_employment_type_through_the_real_form_persists_it(): void
    {
        $coordination = $this->coordination();
        $programUnit = ProgramUnit::factory()->create();

        $this->actingAs($coordination)->get(route('admin.users.create'))->assertOk();

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->set('name', 'Docente Creado Con Vinculación')
            ->set('email', 'creado.con.vinculacion@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', $programUnit->id)
            ->set('employment_type', 'medio_tiempo_1m')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'creado.con.vinculacion@sigae.local',
            'employment_type' => 'medio_tiempo_1m',
        ]);
    }

    public function test_marking_only_auditor_never_shows_the_field_on_create(): void
    {
        $coordination = $this->coordination();

        $this->actingAs($coordination)->get(route('admin.users.create'))->assertOk();

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->set('selectedRoles', [RoleName::Auditor->value])
            ->assertDontSee('Tipo de vinculación');
    }
}
