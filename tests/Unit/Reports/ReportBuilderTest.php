<?php

namespace Tests\Unit\Reports;

use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_report_lists_assignment_hours_only_as_information_and_computes_compliance(): void
    {
        $teacher = User::factory()->create(['name' => 'Ana Docente']);
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create(['name' => 'Clases teóricas']);
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
            'assigned_hours' => 5,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'is_mandatory' => true,
        ]);

        Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Approved,
        ]);

        $report = ReportBuilder::teacher($teacher, $period);

        $assignmentRow = $report['sections'][0]['rows'][0];
        $this->assertEquals('5', $assignmentRow[3]);

        $this->assertEquals('100%', $report['summary']['value']);
        $this->assertStringContainsString('1 de 1', $report['summary']['detail']);

        // El título (para pantalla/documento) sí lleva el nombre; el
        // identificador de archivo usa el ID en su lugar — dato personal
        // fuera del nombre del archivo descargable.
        $this->assertStringContainsString('Ana Docente', $report['title']);
        $this->assertStringContainsString((string) $teacher->id, $report['file_identifier']);
        $this->assertStringNotContainsString('Ana', $report['file_identifier']);
        $this->assertStringNotContainsString('Docente', $report['file_identifier']);
    }

    public function test_activity_report_computes_per_teacher_compliance_scoped_to_that_activity(): void
    {
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create();
        $teacherA = User::factory()->create(['name' => 'Teacher A']);
        $teacherB = User::factory()->create(['name' => 'Teacher B']);

        foreach ([$teacherA, $teacherB] as $teacher) {
            TeacherAssignment::factory()->create([
                'user_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ]);
        }

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'is_mandatory' => true,
        ]);

        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Pending]);

        $report = ReportBuilder::activity($activity->load('component'), $period);

        $complianceRows = collect($report['sections'][1]['rows'])->keyBy(0);

        $this->assertEquals('100%', $complianceRows['Teacher A'][3]);
        $this->assertEquals('0%', $complianceRows['Teacher B'][3]);
    }

    public function test_cross_cutting_report_only_includes_deliverables_without_an_activity(): void
    {
        $period = AcademicPeriod::factory()->create();
        $commitment = CrossCuttingCommitment::factory()->create(['name' => 'Bienestar']);

        Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => null,
            'cross_cutting_commitment_id' => $commitment->id,
            'name' => 'Encuesta transversal',
        ]);

        // Un entregable normal de actividad en el mismo periodo no debe aparecer aquí.
        Deliverable::factory()->create(['academic_period_id' => $period->id]);

        $report = ReportBuilder::crossCutting($period);

        $names = collect($report['sections'][0]['rows'])->pluck(1);
        $this->assertTrue($names->contains('Encuesta transversal'));
        $this->assertCount(1, $report['sections'][0]['rows']);
    }

    /**
     * A diferencia de teacher(), estos 3 informes no identifican a ninguna
     * persona en su título (actividad/componente, compromiso transversal,
     * o solo el periodo — información institucional) — así que
     * file_identifier no necesita anonimizar nada, y describe lo mismo
     * que title.
     */
    public function test_activity_cross_cutting_and_consolidated_reports_have_no_personal_data_to_anonymize_in_the_file_name(): void
    {
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create(['name' => 'Clases teóricas'])->load('component');
        $commitment = CrossCuttingCommitment::factory()->create(['name' => 'Bienestar']);

        $activityReport = ReportBuilder::activity($activity, $period);
        $crossCuttingReport = ReportBuilder::crossCutting($period, $commitment);
        $consolidatedReport = ReportBuilder::consolidated($period);

        $this->assertArrayHasKey('file_identifier', $activityReport);
        $this->assertStringContainsString('Clases teóricas', $activityReport['file_identifier']);

        $this->assertArrayHasKey('file_identifier', $crossCuttingReport);
        $this->assertStringContainsString('Bienestar', $crossCuttingReport['file_identifier']);

        $this->assertArrayHasKey('file_identifier', $consolidatedReport);
        $this->assertEquals($consolidatedReport['title'], $consolidatedReport['file_identifier']);
    }

    public function test_consolidated_report_includes_the_leader_covering_each_activity(): void
    {
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();
        $teacher = User::factory()->create();
        $leader = User::factory()->create(['name' => 'El Líder']);

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id]);

        $report = ReportBuilder::consolidated($period);

        $row = $report['sections'][0]['rows'][0];
        $this->assertEquals('El Líder', $row[3]);
    }

    public function test_consolidated_report_shows_dash_for_leader_on_cross_cutting_rows(): void
    {
        $period = AcademicPeriod::factory()->create();
        $teacher = User::factory()->create();
        $commitment = CrossCuttingCommitment::factory()->create();

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => null,
            'cross_cutting_commitment_id' => $commitment->id,
        ]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id]);

        $report = ReportBuilder::consolidated($period);

        $row = $report['sections'][0]['rows'][0];
        $this->assertEquals('—', $row[3]);
        $this->assertEquals('Transversal', $row[1]);
    }
}
