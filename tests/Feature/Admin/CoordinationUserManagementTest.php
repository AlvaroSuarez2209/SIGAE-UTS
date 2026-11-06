<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora, punto 8: Coordinación obtiene su propia
 * pantalla de Usuarios, limitada a Docente/Líder/Auditor — nunca puede
 * ver, editar ni asignar un rol Administrador o Coordinación, ni
 * siquiera a una cuenta sin ningún rol todavía (eso sigue siendo
 * create()). El lado de Administración (punto 7) tiene su propio archivo:
 * UserRoleScopeTest.
 */
class CoordinationUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $attributes));
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    public function test_coordination_can_view_the_user_list(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/users')->assertOk();
    }

    public function test_coordination_sees_the_usuarios_menu_link(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/dashboard')->assertSee('Usuarios');
    }

    public function test_teacher_does_not_see_the_usuarios_menu_link(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/dashboard')->assertDontSee('Usuarios');
    }

    /**
     * "Coordinación no puede... ni escalar roles de ningún usuario" — el
     * formulario de creación solo le ofrece Docente/Líder/Auditor.
     */
    public function test_the_create_form_only_offers_teacher_leader_and_auditor_to_coordination(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $html = Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->html();

        $this->assertStringContainsString('value="'.RoleName::Teacher->value.'"', $html);
        $this->assertStringContainsString('value="'.RoleName::Leader->value.'"', $html);
        $this->assertStringContainsString('value="'.RoleName::Auditor->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Administrator->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Coordination->value.'"', $html);
    }

    public function test_coordination_cannot_create_a_user_with_the_administrator_role(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->set('name', 'Intento Admin')
            ->set('email', 'intento.admin@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Administrator->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertDatabaseMissing('users', ['email' => 'intento.admin@sigae.local']);
    }

    public function test_coordination_cannot_escalate_an_existing_user_to_coordination(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);

        Livewire::actingAs($coordination)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertFalse($teacher->fresh()->hasRole(RoleName::Coordination));
    }

    /**
     * "Coordinación no puede crear, editar ni asignar roles Administrador
     * o Coordinación" — ni siquiera puede VER el formulario de edición de
     * una cuenta que ya tiene uno de esos dos roles.
     */
    public function test_coordination_cannot_view_or_edit_an_administrator_account(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $otherAdmin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($coordination)->get("/admin/users/{$otherAdmin->id}/edit")->assertForbidden();
    }

    public function test_coordination_cannot_view_or_edit_another_coordination_account(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $otherCoordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get("/admin/users/{$otherCoordination->id}/edit")->assertForbidden();
    }

    public function test_coordination_cannot_toggle_an_administrators_active_status(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($coordination)
            ->test(UserIndex::class)
            ->call('toggleActive', $admin)
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    /**
     * El listado y el filtro de rol de Coordinación nunca incluyen una
     * cuenta Administrador/Coordinación — ni siquiera en modo de solo
     * lectura (ver UserIndex::isCoordinationOnly()).
     */
    public function test_coordinations_user_list_never_shows_an_administrator_or_coordination_account(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $otherAdmin = $this->userWithRole(RoleName::Administrator, ['name' => 'Otro Administrador']);
        $teacher = $this->userWithRole(RoleName::Teacher, ['name' => 'Docente Visible']);

        $html = Livewire::actingAs($coordination)
            ->test(UserIndex::class)
            ->assertSee('Docente Visible')
            ->html();

        $this->assertStringNotContainsString('Otro Administrador', $html);
    }

    public function test_coordinations_role_filter_never_offers_administrator_or_coordination(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $html = Livewire::actingAs($coordination)
            ->test(UserIndex::class)
            ->html();

        $this->assertStringContainsString('value="'.RoleName::Teacher->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Administrator->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Coordination->value.'"', $html);
    }

    /**
     * Administración, en cambio, sigue viendo a TODOS (punto 7: "el
     * Administrador sigue pudiendo verlos") — el scoping es exclusivo de
     * Coordinación.
     */
    public function test_administrators_user_list_still_shows_every_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->userWithRole(RoleName::Teacher, ['name' => 'Docente Cualquiera']);
        $this->userWithRole(RoleName::Coordination, ['name' => 'Otra Coordinadora']);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->assertSee('Docente Cualquiera')
            ->assertSee('Otra Coordinadora');
    }

    public function test_the_import_teachers_button_is_only_offered_to_coordination(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($coordination)->get('/admin/users')->assertSee('Importar docentes');
        $this->actingAs($admin)->get('/admin/users')->assertDontSee('Importar docentes');
    }
}
