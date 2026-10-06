<?php

namespace Tests\Feature\Authorization;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
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
use Tests\TestCase;

/**
 * Prioridad 1 de la revisión de la directora (acceso y autorización por
 * roles): la verificación manual con las 4 cuentas de demostración (acceso
 * directo por URL a rutas fuera del alcance del rol → 403) ya se hizo y no
 * encontró ningún problema — este archivo es esa misma verificación, ahora
 * como prueba automatizada de regresión, para que quede documentada y no
 * dependa de repetirla a mano. No se tocó ninguna Policy ni middleware para
 * escribir estas pruebas; todas pasan contra el código actual sin cambios.
 *
 * Patrón de autorización confirmado en el código (no se adivinó): las rutas
 * agrupadas bajo el middleware `role:...` (routes/web.php) son protegidas
 * por `App\Http\Middleware\EnsureHasRole`, que hace `abort(403, ...)` — y
 * `EvidencePolicy::review()` (usado por ReviewShow::mount() vía
 * $this->authorize()) deja que Laravel convierta la denegación en la misma
 * respuesta 403 (AuthorizationException). Las dos rutas usan `assertForbidden()`.
 */
class RoleAccessTest extends TestCase
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

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(
            Role::whereIn('name', array_map(fn (RoleName $r) => $r->value, $roles))->pluck('id')
        );

        return $user;
    }

    // --- 1. Docente contra rutas administrativas (usuarios, catálogos, configuración) ---

    public function test_teacher_cannot_access_user_management_via_direct_url(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
    }

    public function test_teacher_cannot_access_catalogs_via_direct_url(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/catalogs/components')->assertForbidden();
    }

    public function test_teacher_cannot_access_academic_periods_via_direct_url(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/periods')->assertForbidden();
    }

    // --- 2. Docente contra la bandeja de revisión ---

    public function test_teacher_cannot_access_review_inbox_via_direct_url(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/reviews')->assertForbidden();
    }

    // --- 3. Líder fuera de su ámbito de liderazgo vigente, cambiando el ID en la URL ---

    public function test_leader_cannot_review_an_evidence_outside_their_leadership_scope_via_direct_url(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacherProgramUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $teacherProgramUnit->id,
        ]);

        // El liderazgo del líder cubre OTRO programa — no el del docente dueño
        // de la evidencia. Cambiar el ID en la URL no debe saltarse esto.
        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'activity_id' => null,
            'program_unit_id' => $otherProgramUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Submitted,
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        $this->actingAs($leader)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    // --- 4. Coordinación contra una acción exclusiva de Administrador ---

    public function test_coordination_cannot_access_user_management_via_direct_url(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/users')->assertForbidden();
    }

    public function test_coordination_cannot_access_audit_log_via_direct_url(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/audit-logs')->assertForbidden();
    }

    // --- 5. Usuario con doble rol accede a las rutas de ambos, sin cuenta duplicada ---

    public function test_a_user_with_both_teacher_and_leader_roles_accesses_routes_of_both_roles(): void
    {
        $user = $this->userWithRoles([RoleName::Teacher, RoleName::Leader]);

        // Ruta propia del rol Docente (envío de evidencias).
        $this->actingAs($user)->get('/my-deliverables')->assertOk();

        // Ruta propia del rol Líder (bandeja de revisión) — protegida por
        // 'role:administrator,coordination,leader' en routes/web.php.
        $this->actingAs($user)->get('/reviews')->assertOk();
    }
}
