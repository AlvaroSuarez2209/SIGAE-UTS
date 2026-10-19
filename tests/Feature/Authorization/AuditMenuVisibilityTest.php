<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo de MyDeliverablesVisibilityTest: el manual de usuario dice que
 * el rol Auditor también tiene acceso a "Auditoría" (y la ruta
 * /admin/audit-logs, 'role:administrator,auditor', ya lo permitía), pero
 * en el sidebar ese enlace vivía dentro del grupo "Administración",
 * gateado solo por $isAdmin — un Auditor sin otro rol nunca lo veía. Se
 * corrigió con $canSeeAudit (administrator o auditor), sin mover ni tocar
 * la condición de Usuarios/Identidad institucional (siguen exclusivos de
 * Administrador) ni ninguna policy/ruta.
 */
class AuditMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(
            Role::whereIn('name', array_map(fn (RoleName $r) => $r->value, $roles))->pluck('id')
        );

        return $user;
    }

    public function test_auditor_only_sees_the_audit_link_but_not_the_rest_of_the_management_group(): void
    {
        $user = $this->userWithRoles([RoleName::Auditor]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Auditoría');
        $response->assertDontSee('Usuarios');
        $response->assertDontSee('Identidad institucional');
        $response->assertDontSee('Periodos');
        $response->assertDontSee('Distribución');
        $response->assertDontSee('Líderes');
        $response->assertDontSee('Entregables');
    }

    public function test_administrator_only_still_sees_the_audit_link(): void
    {
        $user = $this->userWithRoles([RoleName::Administrator]);

        $this->actingAs($user)->get('/dashboard')->assertSee('Auditoría');
    }

    public function test_teacher_only_does_not_see_the_audit_link(): void
    {
        $user = $this->userWithRoles([RoleName::Teacher]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('Auditoría');
    }

    public function test_leader_only_does_not_see_the_audit_link(): void
    {
        $user = $this->userWithRoles([RoleName::Leader]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('Auditoría');
    }

    public function test_coordination_only_does_not_see_the_audit_link(): void
    {
        $user = $this->userWithRoles([RoleName::Coordination]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('Auditoría');
    }
}
