<?php

namespace Tests\Feature\Reports;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Reports\ConsolidatedReport;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConsolidatedReportTest extends TestCase
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
     * Prioridad 6, parte 2: el set completo de filtros (periodo, programa,
     * docente, actividad, líder, estado) se combina con AND. Programa solo
     * incluiría a ambos docentes (mismo programa); agregar actividad encima
     * debe acotar al que de verdad está en esa actividad.
     */
    public function test_filters_combine_with_and_not_or(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $program = ProgramUnit::factory()->create();
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();

        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherA->update(['program_unit_id' => $program->id]);
        $teacherB = $this->userWithRole(RoleName::Teacher);
        $teacherB->update(['program_unit_id' => $program->id]);

        $deliverableA = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id]);
        $deliverableB = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id]);

        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $deliverableA->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $deliverableB->id, 'status' => EvidenceStatus::Approved]);

        $component = Livewire::actingAs($admin)
            ->test(ConsolidatedReport::class)
            ->set('periodFilter', $period->id)
            ->set('programFilter', $program->id);

        $component->assertViewHas('report', fn ($report) => count($report['sections'][0]['rows']) === 2);

        $component->set('activityFilter', $activityA->id)
            ->assertViewHas('report', function ($report) use ($teacherA) {
                $rows = $report['sections'][0]['rows'];

                return count($rows) === 1 && $rows[0][0] === $teacherA->fresh()->name;
            });
    }

    /**
     * Mismo set de filtros, pero leídos directamente de la URL (#[Url]) —
     * confirma que un enlace de drill-down con estos query params realmente
     * arranca el informe ya filtrado, sin que el usuario tenga que volver a
     * seleccionar nada.
     */
    public function test_query_string_filters_prefilter_the_report_on_load(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        // Nombres explícitos (no los aleatorios por defecto del factory):
        // 2 docentes con nombre al azar podrían coincidir entre sí por pura
        // casualidad de Faker, lo que volvería frágil un assertDontSee.
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacher->update(['name' => 'Docente Incluido']);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);
        $otherTeacher->update(['name' => 'Docente Excluido']);

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $otherTeacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Pending]);

        $url = route('reports.consolidated', ['period' => $period->id, 'teacher' => $teacher->id]);

        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();

        // Ambos nombres aparecen en el <select> de "Docente" (lista todas
        // las opciones, correcto) — lo que confirma el filtro es que
        // "Excluido" NO aparece una 2ª vez dentro de los datos del informe,
        // mientras que "Incluido" sí (filtro + fila real).
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Docente Excluido'), 'Solo debería aparecer como opción del filtro, nunca en los datos del informe.');
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'Docente Incluido'), 'Debería aparecer como opción del filtro Y en los datos del informe.');
    }

    public function test_status_filter_option_list_never_includes_deliverable_status_values(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $html = Livewire::actingAs($admin)->test(ConsolidatedReport::class)->html();

        $this->assertStringNotContainsString('Publicado</option>', $html);
    }

    /**
     * Caso extremo simulado a mano (un Borrador real nunca llega a tener
     * evidencia por sí solo). El Resumen por docente (% de avance) nunca
     * debe contar un entregable Borrador, sin importar el filtro de estado.
     */
    public function test_evidence_status_filter_never_lets_a_draft_deliverable_affect_compliance(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $teacher = $this->userWithRole(RoleName::Teacher);

        $published = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        $published->recipients()->attach($teacher->id);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $published->id, 'status' => EvidenceStatus::Approved]);

        $draft = Deliverable::factory()->draft()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $draft->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(ConsolidatedReport::class)
            ->set('periodFilter', $period->id)
            ->set('statusFilter', EvidenceStatus::Approved->value)
            ->assertViewHas('report', function ($report) {
                $row = $report['sections'][2]['rows'][0];

                // [Docente, Aprobados, Obligatorios, % Avance]
                return $row[2] === 1;
            });
    }
}
