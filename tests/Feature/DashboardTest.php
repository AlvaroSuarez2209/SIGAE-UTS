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
}
