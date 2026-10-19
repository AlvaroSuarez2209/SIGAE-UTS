<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo de la directora: Líder y Coordinación veían la opción "Mis
 * entregables" en el menú lateral aunque esa pantalla es exclusiva del rol
 * Docente (manual de usuario, sección "Docente") — el enlace en
 * resources/views/layouts/app.blade.php no tenía ninguna condición de rol.
 * Se corrigió ahí (ahora depende de $isTeacher) y se protegió además la
 * ruta /my-deliverables en sí (routes/web.php, middleware 'role:teacher')
 * — ocultar el enlace no bastaba, la URL directa seguía abierta a
 * cualquier cuenta autenticada. /my-deliverables/{evidence} (ver evidencia
 * puntual) NO se tocó: sigue abierta a Administrador/Coordinación/Auditor
 * y a un Líder dentro de su ámbito, vía EvidencePolicy::view() — un caso de
 * uso distinto y ya documentado (ver el comentario en routes/web.php).
 */
class MyDeliverablesVisibilityTest extends TestCase
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

    public function test_teacher_only_sees_the_menu_link_and_can_access_the_route(): void
    {
        $user = $this->userWithRoles([RoleName::Teacher]);

        $this->actingAs($user)->get('/dashboard')->assertSee('Mis entregables');
        $this->actingAs($user)->get('/my-deliverables')->assertOk();
    }

    public function test_leader_only_does_not_see_the_menu_link_and_cannot_access_the_route(): void
    {
        $user = $this->userWithRoles([RoleName::Leader]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('Mis entregables');
        $this->actingAs($user)->get('/my-deliverables')->assertForbidden();
    }

    public function test_coordination_only_does_not_see_the_menu_link_and_cannot_access_the_route(): void
    {
        $user = $this->userWithRoles([RoleName::Coordination]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('Mis entregables');
        $this->actingAs($user)->get('/my-deliverables')->assertForbidden();
    }

    public function test_administrator_only_does_not_see_the_menu_link_and_cannot_access_the_route(): void
    {
        $user = $this->userWithRoles([RoleName::Administrator]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('Mis entregables');
        $this->actingAs($user)->get('/my-deliverables')->assertForbidden();
    }

    public function test_teacher_and_leader_together_sees_the_menu_link_and_can_access_the_route(): void
    {
        $user = $this->userWithRoles([RoleName::Teacher, RoleName::Leader]);

        $this->actingAs($user)->get('/dashboard')->assertSee('Mis entregables');
        $this->actingAs($user)->get('/my-deliverables')->assertOk();
    }
}
