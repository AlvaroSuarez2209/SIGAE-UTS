<?php

namespace Tests\Feature;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Dashboard;
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

class DashboardTest extends TestCase
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

    public function test_teacher_only_sees_the_teacher_panel(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);

        $component = Livewire::actingAs($teacher)->test(Dashboard::class);

        $component->assertViewHas('teacherPanel', fn ($panel) => $panel !== null);
        $component->assertViewHas('leaderPanel', null);
        $component->assertViewHas('coordinationPanel', null);
    }

    public function test_teacher_panel_reports_correct_status_counts_and_compliance(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $approved = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        $pending = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);

        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $approved->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $pending->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertViewHas('teacherPanel', function ($panel) {
                return $panel['counts']['approved'] === 1
                    && $panel['counts']['pending'] === 1
                    && $panel['compliance']['percentage'] === 50.0;
            });
    }

    /**
     * Prioridad 6, punto 3: la dona es un complemento visual de las 7
     * tarjetas KPI, no las reemplaza — este test solo confirma que
     * renderiza con los datos reales, no la geometría SVG exacta.
     */
    public function test_teacher_panel_shows_the_status_distribution_donut_chart(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertSee('Distribución de evidencias por estado')
            ->assertSee('Aprobado');
    }

    public function test_upcoming_deadlines_exclude_already_approved_evidence(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $soonApproved = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'due_at' => now()->addDays(3),
        ]);
        $soonPending = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'due_at' => now()->addDays(5),
        ]);

        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $soonApproved->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $soonPending->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertViewHas('teacherPanel', function ($panel) use ($soonPending) {
                return $panel['upcoming']->count() === 1
                    && $panel['upcoming']->first()->deliverable_id === $soonPending->id;
            });
    }

    public function test_leader_panel_only_shows_their_own_scope(): void
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

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->assertViewHas('leaderPanel', function ($panel) use ($ownTeacher, $otherTeacher) {
                $names = $panel['rows']->pluck('teacher.id');

                return $names->contains($ownTeacher->id) && ! $names->contains($otherTeacher->id);
            });
    }

    /**
     * Prioridad 6, punto 4: KPIs y gráfico de cumplimiento por actividad
     * del líder, calculados en PHP sobre los datos que leaderPanel() ya
     * arma — "% promedio" es ponderado (aprobados/obligatorios en todo el
     * ámbito), no un promedio simple de los % por fila.
     */
    public function test_leader_panel_reports_teacher_count_average_compliance_and_per_activity_breakdown(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();
        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherB = $this->userWithRole(RoleName::Teacher);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);
        TeacherAssignment::factory()->create(['user_id' => $teacherA->id, 'academic_period_id' => $period->id, 'activity_id' => $activityA->id, 'program_unit_id' => $programUnit->id]);
        TeacherAssignment::factory()->create(['user_id' => $teacherB->id, 'academic_period_id' => $period->id, 'activity_id' => $activityB->id, 'program_unit_id' => $programUnit->id]);

        $deliverableA = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id, 'is_mandatory' => true]);
        $deliverableB = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id, 'is_mandatory' => true]);

        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $deliverableA->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $deliverableB->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->assertViewHas('leaderPanel', function ($panel) {
                $percentages = $panel['activityCompliance']->pluck('percentage')->sort()->values()->all();

                return $panel['teacherCount'] === 2
                    && $panel['averageCompliance'] === 50.0
                    && $percentages === [0.0, 100.0];
            });
    }

    /**
     * Caso "sin datos" explícito del diagnóstico: un líder con liderazgo
     * vigente pero sin ninguna asignación docente bajo su ámbito todavía
     * (periodo recién creado) — ni el KPI ni el gráfico deben romperse,
     * deben mostrar un estado vacío razonable.
     */
    public function test_leader_panel_shows_a_reasonable_empty_state_when_scope_has_no_assignments(): void
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

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->assertViewHas('leaderPanel', fn ($panel) => $panel['teacherCount'] === 0
                && $panel['averageCompliance'] === null
                && $panel['activityCompliance']->isEmpty())
            ->assertSee('No hay actividades con entregables obligatorios en tu ámbito todavía.');
    }

    public function test_coordination_panel_visible_to_administrator_and_auditor_but_not_teacher(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $auditor = $this->userWithRole(RoleName::Auditor);
        $teacher = $this->userWithRole(RoleName::Teacher);

        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        Livewire::actingAs($admin)->test(Dashboard::class)->assertViewHas('coordinationPanel', fn ($p) => $p !== null);
        Livewire::actingAs($auditor)->test(Dashboard::class)->assertViewHas('coordinationPanel', fn ($p) => $p !== null);
        Livewire::actingAs($teacher)->test(Dashboard::class)->assertViewHas('coordinationPanel', null);
    }

    public function test_coordination_panel_upcoming_deadlines_count_pending_recipients_across_all_teachers(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherB = $this->userWithRole(RoleName::Teacher);

        $dueSoon = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'due_at' => now()->addDays(5),
        ]);
        $dueLater = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'due_at' => now()->addDays(30),
        ]);

        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $dueSoon->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $dueSoon->id, 'status' => EvidenceStatus::Pending]);
        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $dueLater->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('coordinationPanel', function ($panel) use ($dueSoon) {
                $upcoming = $panel['upcoming'];

                return $upcoming->count() === 1
                    && $upcoming->first()['deliverable']->id === $dueSoon->id
                    && $upcoming->first()['total'] === 2
                    && $upcoming->first()['pending'] === 1;
            });
    }

    /**
     * Prioridad 6: "Próximos vencimientos" debe mostrar primero lo que de
     * verdad necesita atención (pendientes reales), sin importar su fecha
     * límite, y solo después lo ya resuelto (todo enviado) — dentro de
     * cada uno de esos 2 grupos, por fecha límite ascendente. Antes se
     * ordenaba solo por fecha, lo que enterraba un pendiente urgente entre
     * filas ya cerradas con fecha más próxima.
     */
    public function test_coordination_panel_upcoming_deadlines_shows_pending_first_then_sent_each_ordered_by_due_date(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $teacher = $this->userWithRole(RoleName::Teacher);

        // Deliberadamente "fuera de orden" por fecha: si solo se ordenara
        // por due_at, el resultado sería sentSoon, pendingSoon, pendingLate,
        // sentLate — mezclando pendientes y resueltos.
        $pendingLate = Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDays(10)]);
        $pendingSoon = Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDays(2)]);
        $sentLate = Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDays(12)]);
        $sentSoon = Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDay()]);

        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $pendingLate->id, 'status' => EvidenceStatus::Pending]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $pendingSoon->id, 'status' => EvidenceStatus::Pending]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $sentLate->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $sentSoon->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('coordinationPanel', function ($panel) use ($pendingSoon, $pendingLate, $sentSoon, $sentLate) {
                $ids = $panel['upcoming']->map(fn ($row) => $row['deliverable']->id)->values()->all();

                return $ids === [$pendingSoon->id, $pendingLate->id, $sentSoon->id, $sentLate->id];
            });
    }

    /**
     * Prioridad 6, punto 5: KPI global y gráfico de cumplimiento por
     * programa, calculados en PHP sobre $teacherRows (ya construido por
     * coordinationPanel()) agrupado por User::program_unit_id.
     */
    public function test_coordination_panel_reports_global_compliance_and_per_program_breakdown(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programA = ProgramUnit::factory()->create(['name' => 'Programa A']);
        $programB = ProgramUnit::factory()->create(['name' => 'Programa B']);

        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherA->update(['program_unit_id' => $programA->id]);
        $teacherB = $this->userWithRole(RoleName::Teacher);
        $teacherB->update(['program_unit_id' => $programB->id]);

        $deliverableA = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        $deliverableA->recipients()->attach($teacherA->id);
        $deliverableB = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        $deliverableB->recipients()->attach($teacherB->id);

        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $deliverableA->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $deliverableB->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('coordinationPanel', function ($panel) {
                $byLabel = $panel['programCompliance']->keyBy('label');

                return $panel['globalCompliance'] === 50.0
                    && $byLabel->get('Programa A')['percentage'] === 100.0
                    && $byLabel->get('Programa B')['percentage'] === 0.0;
            });
    }

    /**
     * Caso "sin datos" explícito del diagnóstico: un periodo recién creado
     * sin ningún entregable obligatorio todavía — el KPI global debe
     * mostrarse como "sin datos" (null), no como 0%, que significaría
     * "hay obligatorios y ninguno aprobado" (una situación distinta).
     */
    public function test_coordination_panel_global_compliance_is_null_when_nothing_to_measure_yet(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('coordinationPanel', fn ($panel) => $panel['globalCompliance'] === null
                && $panel['programCompliance']->isEmpty())
            ->assertSee('No hay docentes con entregables obligatorios en este periodo todavía.');
    }

    /**
     * Revisión de la directora, punto 7 del diagnóstico de Prioridad 6:
     * confirma que los indicadores NUEVOS (no solo los que ya existían)
     * excluyen Borradores desde el diseño. Simula el peor caso —una
     * evidencia adjuntada a mano a un entregable Borrador, algo que el
     * flujo real nunca produce por sí solo (un Borrador no sincroniza
     * destinatarios ni crea evidencia)— para confirmar que ni así se cuela
     * en el % de cumplimiento del líder ni en el de coordinación.
     */
    public function test_draft_deliverables_never_affect_the_new_compliance_indicators(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();
        $teacher = $this->userWithRole(RoleName::Teacher);

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

        $draftDeliverable = Deliverable::factory()->draft()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'is_mandatory' => true,
        ]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $draftDeliverable->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->assertViewHas('leaderPanel', fn ($panel) => $panel['averageCompliance'] === null
                && $panel['activityCompliance']->first()['percentage'] === null);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertViewHas('coordinationPanel', fn ($panel) => $panel['globalCompliance'] === null
                && $panel['programCompliance']->isEmpty());
    }

    public function test_due_label_marks_today_and_tomorrow_as_warning(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $component = Livewire::actingAs($teacher)->test(Dashboard::class)->instance();

        $this->assertSame(['label' => 'Vence hoy', 'warning' => true], $component->dueLabel(now()));
        $this->assertSame(['label' => 'Vence mañana', 'warning' => true], $component->dueLabel(now()->addDay()));
        $this->assertSame(['label' => 'Vence en 5 días', 'warning' => false], $component->dueLabel(now()->addDays(5)));
    }

    public function test_upcoming_deadlines_section_title_no_longer_shows_the_day_window(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDays(3)]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertSee('Próximos vencimientos')
            ->assertDontSee('Próximos vencimientos (14 días)');
    }

    public function test_teacher_panel_shows_the_days_remaining_badge_per_row(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDays(3)]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Pending]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertSee('Vence en 3 días');
    }

    public function test_coordination_panel_shows_a_warning_badge_for_a_deliverable_due_tomorrow(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        Deliverable::factory()->create(['academic_period_id' => $period->id, 'due_at' => now()->addDay()]);

        $html = Livewire::actingAs($admin)->test(Dashboard::class)->html();

        $this->assertStringContainsString('Vence mañana', $html);
        $this->assertStringContainsString('bg-status-warning-subtle text-status-warning', $html);
    }

    /**
     * Antes, coordinationPanel() hacía 2 consultas nuevas POR docente
     * (Deliverable::where(...)->get() + la consulta interna de
     * ComplianceCalculator::forUser()) — con 10 docentes, ~22 consultas.
     * Ahora el número de consultas es constante, sin importar cuántos
     * docentes haya. El umbral (10) es generoso a propósito: lo que
     * importa es que NO crezca con la cantidad de docentes, no un número
     * exacto frágil ante cualquier cambio menor.
     */
    public function test_coordination_panel_query_count_does_not_grow_with_teacher_count(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        for ($i = 0; $i < 10; $i++) {
            $teacher = $this->userWithRole(RoleName::Teacher);
            $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
            $deliverable->recipients()->attach($teacher->id);
            Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Pending]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($admin)->test(Dashboard::class);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Umbral generoso: cubre el render() completo del Dashboard (mount,
        // el <select> de periodos, los 3 chequeos de rol, y desde Prioridad
        // 6 también las 4 listas de opciones de los filtros nuevos del
        // panel de Coordinación — programUnits/filterableTeachers/
        // filterableActivities/filterableLeaders, cada una 1 sola consulta
        // fija sin importar cuántos docentes haya) — lo que importa es que
        // no escale con la cantidad de docentes, no un número exacto y frágil.
        $this->assertLessThan(20, $queryCount, "Se esperaban menos de 20 consultas independientemente del número de docentes, hubo {$queryCount}.");
    }

    /**
     * Mismo caso que arriba, para leaderPanel(): antes, por cada docente
     * único bajo el ámbito del líder, se hacían 2 consultas nuevas más las
     * de pendingReviewCount (isReviewableBy() en memoria).
     */
    public function test_leader_panel_query_count_does_not_grow_with_teacher_count(): void
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

            $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id, 'is_mandatory' => true]);
            Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Submitted]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($leader)->test(Dashboard::class);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queryCount, "Se esperaban menos de 15 consultas independientemente del número de docentes, hubo {$queryCount}.");
    }

    public function test_switching_the_period_filter_updates_all_panels(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $activePeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $archivedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);

        $currentDeliverable = Deliverable::factory()->create(['academic_period_id' => $activePeriod->id]);
        $pastDeliverable = Deliverable::factory()->create(['academic_period_id' => $archivedPeriod->id]);

        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $currentDeliverable->id]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $pastDeliverable->id]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertSet('periodFilter', $activePeriod->id)
            ->set('periodFilter', $archivedPeriod->id)
            ->assertViewHas('teacherPanel', fn ($panel) => $panel['counts']->sum() === 1);
    }

    /**
     * Prioridad 6, parte 2: los 5 filtros del panel de Coordinación se
     * combinan con AND, no con OR — programa solo ya incluiría a los 2
     * docentes (mismo programa); agregar actividad encima debe acotar a
     * solo el que de verdad está en esa actividad.
     */
    public function test_coordination_filters_combine_with_and_not_or(): void
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

        TeacherAssignment::factory()->create(['user_id' => $teacherA->id, 'academic_period_id' => $period->id, 'activity_id' => $activityA->id]);
        TeacherAssignment::factory()->create(['user_id' => $teacherB->id, 'academic_period_id' => $period->id, 'activity_id' => $activityB->id]);

        $deliverableA = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id, 'is_mandatory' => true]);
        $deliverableA->recipients()->attach($teacherA->id);
        $deliverableB = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id, 'is_mandatory' => true]);
        $deliverableB->recipients()->attach($teacherB->id);

        Evidence::factory()->create(['user_id' => $teacherA->id, 'deliverable_id' => $deliverableA->id, 'status' => EvidenceStatus::Approved]);
        Evidence::factory()->create(['user_id' => $teacherB->id, 'deliverable_id' => $deliverableB->id, 'status' => EvidenceStatus::Approved]);

        $component = Livewire::actingAs($admin)->test(Dashboard::class)
            ->set('periodFilter', $period->id)
            ->set('programFilter', $program->id);

        $component->assertViewHas('coordinationPanel', fn ($panel) => $panel['teacherRows']->count() === 2);

        $component->set('activityFilter', $activityA->id)
            ->assertViewHas('coordinationPanel', fn ($panel) => $panel['teacherRows']->count() === 1
                && $panel['teacherRows']->first()['teacher']->id === $teacherA->id);
    }

    public function test_evidence_status_filter_select_never_lists_deliverable_status_options(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        // El filtro "Estado" del Dashboard es sobre EvidenceStatus; el
        // estado de un ENTREGABLE (Borrador/Publicado) nunca debe
        // exponerse fuera de DeliverableIndex.
        $html = Livewire::actingAs($admin)->test(Dashboard::class)->html();

        $this->assertStringNotContainsString('Publicado</option>', $html);
    }

    /**
     * Caso extremo simulado a mano (un Borrador real nunca llega a tener
     * evidencia por sí solo: no sincroniza destinatarios). Confirma que,
     * sin importar qué valor tenga el filtro de estado de evidencia —
     * incluido 'draft', el valor de EvidenceStatus::Draft, que por
     * coincidencia comparte el string 'draft' con DeliverableStatus::Draft
     * pero es un concepto completamente distinto—, el entregable Borrador
     * nunca suma al % de avance.
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

        foreach (['', EvidenceStatus::Approved->value, EvidenceStatus::Draft->value] as $statusValue) {
            Livewire::actingAs($admin)->test(Dashboard::class)
                ->set('periodFilter', $period->id)
                ->set('evidenceStatusFilter', $statusValue)
                ->assertViewHas('coordinationPanel', fn ($panel) => $panel['teacherRows']->first()['compliance']['total'] === 1);
        }
    }

    public function test_teacher_panel_status_kpis_and_donut_link_to_my_deliverables_prefiltered_by_status(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($teacher)->test(Dashboard::class)
            ->assertSee(route('my-deliverables.index', ['status' => EvidenceStatus::Approved->value]));
    }

    /**
     * Revisión del estado Exento, punto 3: esta barra enlazaba a
     * reports.activity, pero /reports/* no admite el rol Líder
     * (role:administrator,coordination,auditor) — era un enlace que
     * siempre terminaba en 403 para quien ve este panel. Corregido para
     * que la barra se muestre sin vínculo (<x-bar-chart> ya lo admite).
     */
    public function test_leader_activity_compliance_bar_has_no_link_since_the_leader_cannot_open_the_activity_report(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();
        $teacher = $this->userWithRole(RoleName::Teacher);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);
        TeacherAssignment::factory()->create(['user_id' => $teacher->id, 'academic_period_id' => $period->id, 'activity_id' => $activity->id, 'program_unit_id' => $programUnit->id]);

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id, 'is_mandatory' => true]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->assertDontSee(route('reports.activity', ['period' => $period->id, 'activity' => $activity->id]));
    }

    /**
     * Revisión del estado Exento, punto 1: tarjeta "Exentas" del panel del
     * Líder — cuenta solo las evidencias exentas de SU ámbito vigente en
     * el periodo filtrado, reutilizando $evidencesByUser/
     * $deliverablesByActivity sin ninguna consulta nueva. Las exentas de
     * otro líder, de otra actividad del mismo docente, o de otro periodo,
     * no deben sumar.
     */
    public function test_leader_panel_exempt_count_only_includes_own_scope_and_period(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $otherLeader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $otherPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);
        $programUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();
        $ownActivity = Activity::factory()->create();
        $otherActivity = Activity::factory()->create();

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);
        Leadership::factory()->create([
            'user_id' => $otherLeader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $otherProgramUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $ownTeacher = $this->userWithRole(RoleName::Teacher);
        TeacherAssignment::factory()->create([
            'user_id' => $ownTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $ownActivity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        // Exenta real, dentro del ámbito y periodo del líder: debe contar.
        $ownDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $ownActivity->id]);
        Evidence::factory()->create(['user_id' => $ownTeacher->id, 'deliverable_id' => $ownDeliverable->id, 'status' => EvidenceStatus::Exempt]);

        // Mismo docente, pero una evidencia en OTRA actividad que este
        // líder no lidera (deliberadamente sin TeacherAssignment que la
        // ligue al ámbito del líder) — no debe sumar al conteo.
        $outsideDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $otherActivity->id]);
        Evidence::factory()->create(['user_id' => $ownTeacher->id, 'deliverable_id' => $outsideDeliverable->id, 'status' => EvidenceStatus::Exempt]);

        // Docente de OTRO líder: no debe sumar al conteo de $leader.
        $otherTeacher = $this->userWithRole(RoleName::Teacher);
        TeacherAssignment::factory()->create([
            'user_id' => $otherTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $otherActivity->id,
            'program_unit_id' => $otherProgramUnit->id,
        ]);
        $otherLeaderDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $otherActivity->id]);
        Evidence::factory()->create(['user_id' => $otherTeacher->id, 'deliverable_id' => $otherLeaderDeliverable->id, 'status' => EvidenceStatus::Exempt]);

        // Mismo docente y actividad, pero en OTRO periodo: no debe sumar.
        TeacherAssignment::factory()->create([
            'user_id' => $ownTeacher->id,
            'academic_period_id' => $otherPeriod->id,
            'activity_id' => $ownActivity->id,
            'program_unit_id' => $programUnit->id,
        ]);
        $otherPeriodDeliverable = Deliverable::factory()->create(['academic_period_id' => $otherPeriod->id, 'activity_id' => $ownActivity->id]);
        Evidence::factory()->create(['user_id' => $ownTeacher->id, 'deliverable_id' => $otherPeriodDeliverable->id, 'status' => EvidenceStatus::Exempt]);

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('leaderPanel', fn ($panel) => $panel['exemptCount'] === 1);
    }

    /**
     * Revisión del estado Exento, punto 1: la tarjeta debe mostrarse
     * siempre, incluso en 0 — para que el Líder sepa que la función
     * existe aunque hoy no tenga ninguna evidencia exenta.
     */
    public function test_leader_panel_exempt_card_is_always_visible_and_links_to_the_exempt_list(): void
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

        Livewire::actingAs($leader)->test(Dashboard::class)
            ->set('periodFilter', $period->id)
            ->assertViewHas('leaderPanel', fn ($panel) => $panel['exemptCount'] === 0)
            ->assertSee('Exentas')
            ->assertSee(route('reviews.exempt', ['period' => $period->id]));
    }

    public function test_coordination_drill_down_links_point_to_the_right_prefiltered_reports(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $program = ProgramUnit::factory()->create();
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacher->update(['program_unit_id' => $program->id]);

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'is_mandatory' => true]);
        $deliverable->recipients()->attach($teacher->id);
        Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id, 'status' => EvidenceStatus::Approved]);

        $component = Livewire::actingAs($admin)->test(Dashboard::class)->set('periodFilter', $period->id);

        // KPI "% cumplimiento global" -> Consolidado solo con el periodo.
        $component->assertSee(route('reports.consolidated', ['period' => $period->id]));

        // Tarjeta KPI por estado (Aprobado) -> Consolidado con periodo + estado.
        $component->assertSee(route('reports.consolidated', ['period' => $period->id, 'status' => EvidenceStatus::Approved->value]));

        // Barra de cumplimiento por programa -> Consolidado con periodo + programa.
        $component->assertSee(route('reports.consolidated', ['period' => $period->id, 'program' => $program->id]));

        // Fila de "Consolidado por docente" -> Informe individual con periodo + docente.
        $component->assertSee(route('reports.teacher', ['period' => $period->id, 'teacher' => $teacher->id]));
    }
}
