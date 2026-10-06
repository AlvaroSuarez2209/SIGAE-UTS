<?php

namespace Tests\Feature;

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
 * Revisión del estado Exento, punto 3: antes, la barra "Cumplimiento por
 * actividad" del panel del Líder enlazaba a reports.activity, una ruta
 * que ese rol no puede abrir (role:administrator,coordination,auditor) —
 * un 403 esperando a que alguien hiciera clic. Este test recorre cada
 * <a href> realmente renderizado en el Dashboard para cada rol y confirma
 * que seguirlo nunca devuelve 403 — debe fallar si en el futuro se
 * reintroduce un enlace así, sin tener que enumerar cada enlace a mano.
 */
class DashboardLinksTest extends TestCase
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

    /**
     * @return array<int, string> hrefs únicos, decodificados, que apuntan
     *                            dentro de la propia aplicación
     */
    private function internalLinksIn(string $html): array
    {
        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $html, $matches);

        return collect($matches[1])
            ->map(fn (string $href) => html_entity_decode($href))
            ->filter(fn (string $href) => str_starts_with($href, url('/')))
            ->unique()
            ->values()
            ->all();
    }

    public function test_no_dashboard_link_returns_forbidden_for_any_role(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

        // Un docente con evidencia real, para que el panel docente muestre
        // sus 7 tarjetas por estado, la dona y la fila "Ver" de vencimientos.
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacherDeliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'due_at' => now()->addDays(5),
        ]);
        Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $teacherDeliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        // Un líder con asignación real bajo su ámbito, para que el panel
        // líder muestre la barra de cumplimiento por actividad y la
        // tarjeta "Exentas".
        $leader = $this->userWithRole(RoleName::Leader);
        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);
        $ledTeacher = $this->userWithRole(RoleName::Teacher);
        TeacherAssignment::factory()->create([
            'user_id' => $ledTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);
        $ledDeliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'is_mandatory' => true,
        ]);
        Evidence::factory()->create([
            'user_id' => $ledTeacher->id,
            'deliverable_id' => $ledDeliverable->id,
            'status' => EvidenceStatus::Approved,
        ]);

        // El docente y el líder de los fixtures de arriba YA tienen el
        // ámbito/datos reales que hacen aparecer sus enlaces (tarjetas por
        // estado, barra de cumplimiento, "Exentas") — probar con un
        // usuario nuevo y vacío de ese mismo rol dejaría esos paneles sin
        // renderizar nada, sin ejercitar los enlaces que de verdad importa
        // revisar. Administrador/Coordinación/Auditor no necesitan ningún
        // dato propio: su panel ya existe con los fixtures compartidos.
        $usersByRole = [
            RoleName::Teacher->value => $teacher,
            RoleName::Leader->value => $leader,
            RoleName::Administrator->value => $this->userWithRole(RoleName::Administrator),
            RoleName::Coordination->value => $this->userWithRole(RoleName::Coordination),
            RoleName::Auditor->value => $this->userWithRole(RoleName::Auditor),
        ];

        foreach ($usersByRole as $roleValue => $user) {
            $response = $this->actingAs($user)->get('/dashboard');
            $response->assertOk();

            $links = $this->internalLinksIn($response->getContent());
            $this->assertNotEmpty($links, "No se encontró ningún enlace interno en el Dashboard para el rol {$roleValue}.");

            foreach ($links as $href) {
                $followed = $this->actingAs($user)->get($href);

                $this->assertNotEquals(
                    403,
                    $followed->status(),
                    "El enlace {$href} del Dashboard devuelve 403 para el rol {$roleValue}."
                );
            }
        }
    }
}
