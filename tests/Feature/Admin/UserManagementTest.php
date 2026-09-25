<?php

namespace Tests\Feature\Admin;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
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

    public function test_removing_teacher_role_is_blocked_when_user_has_pending_evidences(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Teacher));
    }

    public function test_removing_teacher_role_is_allowed_when_no_pending_evidences(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($teacher->fresh()->hasRole(RoleName::Teacher));
    }

    /**
     * Réplica del alcance exacto que usa User::canLeadAssignment() —
     * período + programa + actividad, dentro de starts_at/ends_at — para
     * dejar una revisión "Submitted" realmente pendiente bajo el
     * liderazgo del usuario que se le intenta quitar el rol Líder.
     */
    private function submittedEvidenceUnderLeadership(User $leader): Evidence
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        return Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Submitted,
        ]);
    }

    public function test_removing_leader_role_is_blocked_when_user_has_pending_reviews(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $this->submittedEvidenceUnderLeadership($leader);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $leader])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertTrue($leader->fresh()->hasRole(RoleName::Leader));
    }

    public function test_removing_leader_role_is_allowed_when_no_pending_reviews(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $evidence = $this->submittedEvidenceUnderLeadership($leader);
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $leader])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($leader->fresh()->hasRole(RoleName::Leader));
    }

    public function test_adding_a_role_is_never_blocked_by_pending_work(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Teacher->value, RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Teacher));
        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Coordination));
    }

    public function test_role_removal_guard_is_skipped_when_deactivating_the_user(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('is_active', false)
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $teacher->refresh();
        $this->assertFalse($teacher->is_active);
        $this->assertFalse($teacher->hasRole(RoleName::Teacher));
    }
}
