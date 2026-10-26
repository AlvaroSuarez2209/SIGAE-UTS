<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Reviews\ReviewInbox;
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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ReviewInbox filtraba "evidencias revisables por mí" trayendo TODAS las
 * evidencias Submitted del periodo y llamando isReviewableBy() (2
 * consultas nuevas por evidencia) una por una en PHP — ahora es
 * Evidence::scopeReviewableBy(), un JOIN. Estos tests verifican que el
 * resultado sigue siendo el mismo (solo lo que cae en el ámbito del líder)
 * y que el número de consultas no crece con la cantidad de evidencias.
 */
class ReviewInboxTest extends TestCase
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
     * ReviewInbox::render() accede a $evidence->currentVersion->submitted_at
     * — status => Submitted a mano vía factory deja current_version_id en
     * null y rompe la vista; hay que pasar por el flujo real de envío
     * (igual que ReviewShowTest::submittedEvidenceWithLeader()).
     */
    private function submitEvidence(User $teacher, Deliverable $deliverable): Evidence
    {
        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        return $evidence->fresh();
    }

    public function test_leader_only_sees_evidence_within_their_scope(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $ownProgramUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $ownProgramUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $ownTeacher = $this->userWithRole(RoleName::Teacher);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $ownTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityA->id,
            'program_unit_id' => $ownProgramUnit->id,
        ]);
        TeacherAssignment::factory()->create([
            'user_id' => $otherTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityB->id,
            'program_unit_id' => $otherProgramUnit->id,
        ]);

        $ownDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id]);
        $otherDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id]);

        $ownEvidence = $this->submitEvidence($ownTeacher, $ownDeliverable);
        $this->submitEvidence($otherTeacher, $otherDeliverable);

        Livewire::actingAs($leader)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1
                && $pending->first()->id === $ownEvidence->id);
    }

    /**
     * RS-005/RF-027/RF-056 a RF-059: mientras un líder vigente cubra la
     * actividad, sigue siendo exclusiva suya — Administrador no la ve en
     * su bandeja, solo compromisos transversales. Ver
     * test_administrator_also_sees_evidence_from_an_activity_with_no_leader_at_all()
     * para el respaldo real (punto 7).
     */
    public function test_administrator_only_sees_cross_cutting_evidence_when_a_leader_covers_the_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
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
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $activityDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        $crossCuttingDeliverable = Deliverable::factory()->crossCutting()->create(['academic_period_id' => $period->id]);

        $this->submitEvidence($teacher, $activityDeliverable);
        $crossCuttingEvidence = $this->submitEvidence($teacher, $crossCuttingDeliverable);

        Livewire::actingAs($admin)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1
                && $pending->first()->id === $crossCuttingEvidence->id);
    }

    /**
     * Punto 7: respaldo de Administrador para actividades sin ningún
     * líder vigente en este momento — sin esto, esa evidencia quedaría
     * permanentemente sin nadie que la apruebe o devuelva en la bandeja.
     */
    public function test_administrator_also_sees_evidence_from_an_activity_with_no_leader_at_all(): void
    {
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
        // Sin ningún Leadership para esta actividad/programa.

        $activityDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        $orphanEvidence = $this->submitEvidence($teacher, $activityDeliverable);

        Livewire::actingAs($admin)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1
                && $pending->first()->id === $orphanEvidence->id);
    }

    public function test_coordination_sees_nothing_in_the_review_inbox(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->crossCutting()->create(['academic_period_id' => $period->id]);
        $this->submitEvidence($teacher, $deliverable);

        Livewire::actingAs($coordination)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 0);
    }

    public function test_query_count_does_not_grow_with_submitted_evidence_count(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $teacher = $this->userWithRole(RoleName::Teacher);
            $activity = Activity::factory()->create();

            TeacherAssignment::factory()->create([
                'user_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
                'program_unit_id' => $programUnit->id,
            ]);

            $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
            $this->submitEvidence($teacher, $deliverable);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($leader)->test(ReviewInbox::class);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount, "Se esperaban menos de 10 consultas independientemente del número de evidencias, hubo {$queryCount}.");
    }

    public function test_shows_the_late_submission_indicator_for_a_row_submitted_after_the_due_date(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $teacher = $this->userWithRole(RoleName::Teacher);
        $activity = Activity::factory()->create();
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'due_at' => now()->subDays(3),
        ]);
        $this->submitEvidence($teacher, $deliverable);

        Livewire::actingAs($leader)->test(ReviewInbox::class)
            ->assertSee('3 días después de la fecha límite');
    }

    /**
     * Punto 9: dos líderes vigentes sobre la misma actividad y periodo
     * (nada en el modelo lo impide — ver la migración de leaderships, sin
     * ninguna restricción unique). Comportamiento documentado, no
     * ambiguo: ambos ven la evidencia en su bandeja mientras está
     * Enviada; en cuanto UNO decide (aprueba o devuelve), su estado deja
     * de ser Enviada y desaparece de la bandeja de TODOS los demás
     * revisores con el mismo ámbito — el filtro de la bandeja es por
     * estado, no por quién la decidió, así que no hace falta ninguna
     * marca de "en revisión por otro" ni ningún bloqueo adicional: la
     * primera decisión ya resuelve la carrera.
     */
    public function test_two_leaders_over_the_same_scope_both_see_it_until_one_decides(): void
    {
        $leaderA = $this->userWithRole(RoleName::Leader);
        $leaderB = $this->userWithRole(RoleName::Leader);
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

        foreach ([$leaderA, $leaderB] as $leader) {
            Leadership::factory()->create([
                'user_id' => $leader->id,
                'academic_period_id' => $period->id,
                'program_unit_id' => $programUnit->id,
                'activity_id' => null,
                'starts_at' => now()->subMonth(),
                'ends_at' => null,
            ]);
        }

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
        $evidence = $this->submitEvidence($teacher, $deliverable);

        Livewire::actingAs($leaderA)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1);
        Livewire::actingAs($leaderB)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1);

        Livewire::actingAs($leaderA)->test(\App\Livewire\Reviews\ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        Livewire::actingAs($leaderB)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 0);
    }

    public function test_period_filter_defaults_to_the_active_period_when_the_query_string_has_no_filter_at_all(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        Livewire::actingAs($leader)->test(ReviewInbox::class)
            ->assertSet('periodFilter', $period->id);
    }

    /**
     * Antes, mount() pisaba SIEMPRE $periodFilter con el periodo activo,
     * sin importar lo que llegara en la URL — "Todos los periodos"
     * (valor vacío/null) nunca sobrevivía a un "Volver" o a una recarga.
     * Con #[Url], mount() solo aplica el valor por defecto cuando la
     * clave ni siquiera está en el query string.
     */
    public function test_period_filter_persists_all_periods_when_explicitly_present_and_empty_in_the_query_string(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($leader)
            ->test(ReviewInbox::class)
            ->assertSet('periodFilter', null);
    }

    public function test_period_filter_persists_a_specific_period_present_in_the_query_string(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $otherPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);

        Livewire::withQueryParams(['periodFilter' => (string) $otherPeriod->id])
            ->actingAs($leader)
            ->test(ReviewInbox::class)
            ->assertSet('periodFilter', $otherPeriod->id);
    }
}
