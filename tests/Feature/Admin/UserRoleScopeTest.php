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
 * Revisión de la directora, punto 7: "el Administrador crea solo
 * Coordinación y Administrador". UserRoleScopeTest cubre exclusivamente
 * el lado de Administración — qué roles puede ver/asignar en el
 * formulario y qué pasa con una cuenta existente que ya tiene un rol
 * fuera de ese alcance (Docente/Líder/Auditor). El lado de Coordinación
 * (punto 8) tiene su propio archivo una vez se abra esa ruta.
 */
class UserRoleScopeTest extends TestCase
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

    public function test_the_create_form_only_offers_administrator_and_coordination_to_an_administrator(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $html = Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->html();

        $this->assertStringContainsString('value="'.RoleName::Administrator->value.'"', $html);
        $this->assertStringContainsString('value="'.RoleName::Coordination->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Teacher->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Leader->value.'"', $html);
        $this->assertStringNotContainsString('value="'.RoleName::Auditor->value.'"', $html);
    }

    public function test_an_administrator_cannot_create_a_user_with_the_teacher_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Intento Docente')
            ->set('email', 'intento.docente@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertDatabaseMissing('users', ['email' => 'intento.docente@sigae.local']);
    }

    public function test_an_administrator_cannot_create_a_user_with_the_auditor_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Intento Auditor')
            ->set('email', 'intento.auditor@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Auditor->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertDatabaseMissing('users', ['email' => 'intento.auditor@sigae.local']);
    }

    public function test_an_administrator_can_still_create_a_coordination_user(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nueva Coordinadora')
            ->set('email', 'nueva.coordinadora@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(User::where('email', 'nueva.coordinadora@sigae.local')->first()->hasRole(RoleName::Coordination));
    }

    /**
     * "Los usuarios existentes con otros roles no se tocan ni se rompen.
     * El Administrador sigue pudiendo verlos" — un Docente ya existente
     * sigue siendo editable por Administración (p. ej. para su tipo de
     * vinculación en el punto siguiente), aunque ya no pueda crear
     * Docentes nuevos ni quitarle/agregarle ningún otro rol fuera de su
     * alcance.
     */
    public function test_an_administrator_can_still_view_and_edit_an_existing_teacher_without_breaking_their_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('name', 'Nombre Corregido')
            ->call('save')
            ->assertHasNoErrors();

        $teacher->refresh();
        $this->assertEquals('Nombre Corregido', $teacher->name);
        $this->assertTrue($teacher->hasRole(RoleName::Teacher));
    }

    /**
     * El rol que el actor no gestiona nunca aparece como casilla en el
     * formulario de edición — solo una nota informativa, para que
     * Administración no pueda confundirlo con un rol que sí puede marcar
     * o desmarcar.
     */
    public function test_editing_an_existing_teacher_shows_the_locked_role_as_a_note_not_a_checkbox(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);

        $html = Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->assertSee('Docente, que no se puede modificar desde aquí')
            ->html();

        $this->assertStringNotContainsString('value="'.RoleName::Teacher->value.'"', $html);
    }
}
