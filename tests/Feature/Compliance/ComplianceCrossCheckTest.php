<?php

namespace Tests\Feature\Compliance;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Dashboard;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ComplianceCalculator;
use App\Services\Reports\ReportBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cuadro de seguimiento, punto 11: "verifica que las tarjetas, los
 * reportes y el desglose den el mismo porcentaje para el mismo ámbito".
 * Un solo escenario combinado (2 actividades, con un entregable aprobado,
 * uno que requiere ajustes, uno vencido, uno exento y uno opcional),
 * calculado a mano, comparado contra 3 fuentes independientes: el panel
 * de Coordinación del Dashboard (la tarjeta), ReportBuilder::consolidated()
 * (el informe) y una llamada directa a ComplianceCalculator (el
 * desglose aprobados/total).
 */
class ComplianceCrossCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_combined_scenario_yields_the_same_percentage_on_the_dashboard_card_the_report_and_a_direct_call(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::where('name', RoleName::Administrator->value)->first());

        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();

        $teacher = User::factory()->create(['is_active' => true, 'program_unit_id' => $programUnit->id]);
        $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityA->id,
            'program_unit_id' => $programUnit->id,
        ]);
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityB->id,
            'program_unit_id' => $programUnit->id,
        ]);

        // Entregables obligatorios: aprobado, requiere ajustes y vencido
        // cuentan en el denominador (3); el exento se excluye por
        // completo; el opcional nunca entra. A mano: 1 de 3 = 33.3%.
        $approved = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id, 'is_mandatory' => true]);
        $needsAdjustment = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id, 'is_mandatory' => true]);
        $expired = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id, 'is_mandatory' => true]);
        $exempt = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id, 'is_mandatory' => true]);
        $optional = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id, 'is_mandatory' => false]);

        foreach ([$approved, $needsAdjustment, $expired, $exempt, $optional] as $deliverable) {
            $deliverable->recipients()->attach($teacher->id);
        }

        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $approved->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $needsAdjustment->id, 'status' => EvidenceStatus::NeedsAdjustment]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $expired->id, 'status' => EvidenceStatus::Expired]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $exempt->id, 'status' => EvidenceStatus::Exempt]);
        // $optional queda sin ninguna evidencia: no debe hacer falta.

        $expectedPercentage = 33.3;

        // 1) Desglose: llamada directa a ComplianceCalculator.
        $direct = ComplianceCalculator::forUser(
            $teacher,
            collect([$approved, $needsAdjustment, $expired, $exempt, $optional]),
            $teacher->evidences()->get()
        );
        $this->assertEquals($expectedPercentage, $direct['percentage']);
        $this->assertEquals(1, $direct['approved']);
        $this->assertEquals(3, $direct['total']);

        // 2) Tarjeta: panel de Coordinación del Dashboard, filtrado por
        // este mismo docente y periodo.
        $dashboard = Livewire::actingAs($admin)->test(Dashboard::class)
            ->set('periodFilter', $period->id)
            ->set('teacherFilter', $teacher->id);

        $panel = $dashboard->viewData('coordinationPanel');
        $teacherRow = $panel['teacherRows']->firstWhere(fn (array $row) => $row['teacher']->id === $teacher->id);

        $this->assertNotNull($teacherRow);
        $this->assertEquals($expectedPercentage, $teacherRow['compliance']['percentage']);
        $this->assertEquals(1, $teacherRow['compliance']['approved']);
        $this->assertEquals(3, $teacherRow['compliance']['total']);

        // 3) Informe: ReportBuilder::consolidated() para el mismo docente
        // y periodo.
        $report = ReportBuilder::consolidated($period, null, $teacher, null, null, null);
        $reportTeacherRow = collect($report['sections'][2]['rows'])->firstWhere(0, $teacher->name);

        $this->assertNotNull($reportTeacherRow);
        // Columnas: [nombre, aprobados, total, "33.3%"].
        $this->assertSame(1, $reportTeacherRow[1]);
        $this->assertSame(3, $reportTeacherRow[2]);
        $this->assertSame('33.3%', $reportTeacherRow[3]);
    }
}
