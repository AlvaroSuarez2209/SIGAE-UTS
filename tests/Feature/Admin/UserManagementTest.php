<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_administrator_can_view_user_list(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_teacher_cannot_view_user_list(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_leader_cannot_view_user_list(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($leader)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_administrator_can_create_a_user_with_roles(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'nuevo.docente@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'nuevo.docente@sigae.local']);

        $created = User::where('email', 'nuevo.docente@sigae.local')->first();
        $this->assertTrue($created->hasRole(RoleName::Teacher));
    }

    public function test_creating_a_user_requires_at_least_one_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Sin Rol')
            ->set('email', 'sinrol@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [])
            ->call('save')
            ->assertHasErrors('selectedRoles');
    }

    public function test_creating_a_user_with_a_weak_password_fails_validation(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'otro.docente@sigae.local')
            ->set('password', 'password123')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->call('save')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'otro.docente@sigae.local']);
    }

    public function test_password_is_optional_when_editing_an_existing_user(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $existing = $this->userWithRole(RoleName::Teacher);
        $originalPassword = $existing->password;

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $existing])
            ->set('name', 'Nombre Actualizado')
            ->set('password', '')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $existing->refresh();
        $this->assertEquals('Nombre Actualizado', $existing->name);
        $this->assertEquals($originalPassword, $existing->password);
    }

    public function test_administrator_can_toggle_another_users_active_status(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $teacher);

        $this->assertFalse($teacher->fresh()->is_active);
    }

    public function test_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $admin)
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }
}
