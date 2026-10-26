<?php

namespace Tests\Feature\Leaderships;

use App\Enums\RoleName;
use App\Models\Leadership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mi ámbito" (/my-leadership): a diferencia de /leaderships
 * (Administrador/Coordinación, cualquier liderazgo, con acciones), esta
 * pantalla es de solo lectura y exclusiva del rol Líder — cada líder solo
 * ve sus propios liderazgos. Antes no existía ninguna forma de que un
 * líder viera su propio ámbito sin que Coordinación le abriera
 * /leaderships por él.
 */
class MyLeadershipTest extends TestCase
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

    public function test_leader_sees_my_leadership_screen_with_only_their_own_assignments(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $otherLeader = $this->userWithRole(RoleName::Leader);

        $own = Leadership::factory()->create(['user_id' => $leader->id]);
        Leadership::factory()->create(['user_id' => $otherLeader->id]);

        $this->actingAs($leader)->get('/my-leadership')
            ->assertOk()
            ->assertSee($own->programUnit->name)
            ->assertSee($own->academicPeriod->name);

        Livewire::actingAs($leader)->test(\App\Livewire\Leaderships\MyLeadership::class)
            ->assertViewHas('leaderships', fn ($leaderships) => $leaderships->count() === 1
                && $leaderships->first()->id === $own->id);
    }

    public function test_leader_sees_todo_el_programa_when_the_leadership_has_no_specific_activity(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        Leadership::factory()->create(['user_id' => $leader->id, 'activity_id' => null]);

        $this->actingAs($leader)->get('/my-leadership')->assertSee('Todo el programa');
    }

    public function test_leader_sees_whether_the_leadership_is_active_today(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        Leadership::factory()->create([
            'user_id' => $leader->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);
        Leadership::factory()->create([
            'user_id' => $leader->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        $html = $this->actingAs($leader)->get('/my-leadership')->getContent();

        $this->assertStringContainsString('>Sí<', $html);
        $this->assertStringContainsString('>No<', $html);
    }

    public function test_administrator_gets_forbidden_on_the_my_leadership_route(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/my-leadership')->assertForbidden();
    }

    public function test_coordination_gets_forbidden_on_the_my_leadership_route(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/my-leadership')->assertForbidden();
    }

    public function test_teacher_gets_forbidden_on_the_my_leadership_route(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/my-leadership')->assertForbidden();
    }

    public function test_auditor_gets_forbidden_on_the_my_leadership_route(): void
    {
        $auditor = $this->userWithRole(RoleName::Auditor);

        $this->actingAs($auditor)->get('/my-leadership')->assertForbidden();
    }

    public function test_only_the_leader_role_sees_the_my_leadership_link_in_the_sidebar(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $this->actingAs($leader)->get('/dashboard')->assertSee('Mi ámbito');

        foreach ([RoleName::Administrator, RoleName::Coordination, RoleName::Teacher, RoleName::Auditor] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/dashboard')->assertDontSee('Mi ámbito');
        }
    }
}
