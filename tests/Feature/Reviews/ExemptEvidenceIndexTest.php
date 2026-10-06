<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Reviews\ExemptEvidenceIndex;
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

/**
 * Revisión del estado Exento, punto 2: listado de solo lectura de
 * evidencias Exentas. El Líder se acota con el mismo scope
 * `reviewableBy()` que ya usa ReviewInbox; Coordinación y Administrador,
 * en cambio, ven cualquier exención sin acotar por ámbito — este es un
 * listado de consulta global para ellos, no una bandeja de acción.
 */
class ExemptEvidenceIndexTest extends TestCase
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

    public function test_leader_only_sees_exempt_evidence_within_their_scope(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $ownProgramUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

        $ownTeacher = $this->userWithRole(RoleName::Teacher);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        TeacherAssignment::factory()->create([
            'user_id' => $ownTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $ownProgramUnit->id,
        ]);
        TeacherAssignment::factory()->create([
            'user_id' => $otherTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $otherProgramUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $ownProgramUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $ownDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        $ownEvidence = Evidence::factory()->create([
            'user_id' => $ownTeacher->id,
            'deliverable_id' => $ownDeliverable->id,
            'status' => EvidenceStatus::Exempt,
            'exemption_reason' => 'Carga académica adicional.',
        ]);

        $otherDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        Evidence::factory()->create([
            'user_id' => $otherTeacher->id,
            'deliverable_id' => $otherDeliverable->id,
            'status' => EvidenceStatus::Exempt,
            'exemption_reason' => 'No debería verla este líder.',
        ]);

        Livewire::actingAs($leader)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 1 && $exempt->first()->id === $ownEvidence->id)
            ->assertSee('Carga académica adicional.')
            ->assertDontSee('No debería verla este líder.')
            ->assertDontSee('No hay evidencias exentas');
    }

    /**
     * A diferencia de ReviewInbox, donde Administrador solo ve
     * transversales (el respaldo puntual que da `reviewableBy()`), este
     * listado de consulta no está acotado por `reviewableBy()` para
     * Administrador: ve tanto la evidencia de actividad como la
     * transversal.
     */
    public function test_administrator_sees_both_activity_and_cross_cutting_exempt_evidence(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $activityDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);
        $activityEvidence = Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $activityDeliverable->id, 'status' => EvidenceStatus::Exempt]);

        $crossCuttingDeliverable = Deliverable::factory()->crossCutting()->create(['academic_period_id' => $period->id]);
        $crossCuttingEvidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $crossCuttingDeliverable->id,
            'status' => EvidenceStatus::Exempt,
        ]);

        Livewire::actingAs($admin)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 2
                && $exempt->pluck('id')->sort()->values()->all() === collect([$activityEvidence->id, $crossCuttingEvidence->id])->sort()->values()->all());
    }

    /**
     * A diferencia de ReviewInbox (donde `reviewableBy()` nunca incluye a
     * Coordinación, y a Administrador solo lo deja ver transversales —
     * correcto para una bandeja de ACCIÓN, donde esos roles solo respaldan
     * huecos puntuales), este listado es de solo CONSULTA: Coordinación y
     * Administrador necesitan ver cualquier exención de cualquier
     * actividad para hacer seguimiento global, así que para ellos no se
     * aplica `reviewableBy()` en absoluto.
     */
    public function test_coordination_and_administrator_see_exempt_evidence_from_any_activity(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherB = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();

        // Ninguno de los dos tiene liderazgo ni vínculo propio con estas
        // actividades: si todavía se aplicara `reviewableBy()`, ambas
        // consultas devolverían vacío.
        $deliverableA = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id]);
        $evidenceA = Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $deliverableA->id, 'status' => EvidenceStatus::Exempt]);

        $deliverableB = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id]);
        $evidenceB = Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $deliverableB->id, 'status' => EvidenceStatus::Exempt]);

        $this->actingAs($coordination)->get('/reviews/exempt')->assertOk();

        Livewire::actingAs($coordination)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 2
                && $exempt->pluck('id')->sort()->values()->all() === collect([$evidenceA->id, $evidenceB->id])->sort()->values()->all());

        Livewire::actingAs($admin)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 2);
    }

    public function test_leader_still_cannot_see_another_leaders_exempt_evidence(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $otherLeader = $this->userWithRole(RoleName::Leader);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

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

        TeacherAssignment::factory()->create([
            'user_id' => $otherTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $otherProgramUnit->id,
        ]);
        Leadership::factory()->create([
            'user_id' => $otherLeader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $otherProgramUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Exempt,
            'exemption_reason' => 'No debería verla el otro líder.',
        ]);

        $otherDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        $otherLeadersEvidence = Evidence::factory()->create([
            'user_id' => $otherTeacher->id,
            'deliverable_id' => $otherDeliverable->id,
            'status' => EvidenceStatus::Exempt,
        ]);

        Livewire::actingAs($otherLeader)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 1 && $exempt->first()->id === $otherLeadersEvidence->id)
            ->assertDontSee('No debería verla el otro líder.');
    }

    public function test_teacher_and_auditor_cannot_access_the_exempt_list(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $auditor = $this->userWithRole(RoleName::Auditor);

        $this->actingAs($teacher)->get('/reviews/exempt')->assertForbidden();
        $this->actingAs($auditor)->get('/reviews/exempt')->assertForbidden();
    }

    /**
     * Cubre ambos lados del estado vacío: aparece cuando de verdad no hay
     * exentas (ni para el Líder con datos reales en su ámbito, ni para
     * Administrador, que ya no se acota por ámbito) y no aparece cuando sí
     * las hay (ver test_leader_only_sees_exempt_evidence_within_their_scope
     * y los de Administrador/Coordinación más arriba).
     */
    public function test_only_pending_statuses_are_excluded_from_the_list(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

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

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Submitted]);

        $otherDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $otherDeliverable->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($leader)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 0)
            ->assertSee('No hay evidencias exentas');

        Livewire::actingAs($admin)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('exempt', fn ($exempt) => $exempt->count() === 0)
            ->assertSee('No hay evidencias exentas');
    }

    public function test_no_action_buttons_are_rendered_in_the_exempt_list(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

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

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Exempt]);

        $html = Livewire::actingAs($leader)->test(ExemptEvidenceIndex::class)
            ->set('periodFilter', $period->id)
            ->html();

        $this->assertStringNotContainsString('Aprobar', $html);
        $this->assertStringNotContainsString('Devolver', $html);
        $this->assertStringNotContainsString('Quitar exención', $html);
    }
}
