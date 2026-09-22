<?php

namespace Tests\Feature\Audit;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Auth\Login;
use App\Livewire\Periods\PeriodIndex;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Review;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_successful_login_is_recorded(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'user_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_failed_login_is_recorded_with_the_attempted_email_but_no_actor(): void
    {
        $user = User::factory()->create(['email' => 'someone@sigae.local']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');

        $log = AuditLog::where('action', 'login_failed')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertEquals('someone@sigae.local', $log->metadata['email']);
    }

    public function test_login_blocked_for_inactive_account_is_recorded(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login_blocked_inactive',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_logout_is_recorded(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_a_user_is_recorded_without_leaking_the_password_hash(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $newUser = User::factory()->create(['password' => 'plain-text-should-not-appear']);

        $log = AuditLog::where('action', 'created')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $newUser->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->metadata);
    }

    public function test_updating_a_user_password_does_not_leak_the_hash_in_the_audit_log(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $user = $this->userWithRole(RoleName::Teacher);

        $user->update(['password' => 'a-new-plain-password']);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_id', $user->id)
            ->where('auditable_type', User::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('changes', $log->metadata);
        $this->assertEquals(['password'], $log->metadata['redacted_fields']);
        $this->assertStringNotContainsString('a-new-plain-password', json_encode($log->metadata));
    }

    public function test_updating_a_user_name_and_password_together_logs_the_name_and_redacts_the_password(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $user = $this->userWithRole(RoleName::Teacher);

        $user->update(['name' => 'Nombre Nuevo', 'password' => 'otra-clave-en-texto-plano']);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_id', $user->id)
            ->where('auditable_type', User::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Nombre Nuevo', $log->metadata['changes']['name']);
        $this->assertArrayNotHasKey('password', $log->metadata['changes']);
        $this->assertEquals(['password'], $log->metadata['redacted_fields']);
    }

    public function test_reopening_a_closed_period_is_recorded(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);

        Livewire::actingAs($admin)->test(PeriodIndex::class)->call('reopen', $period);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_type', AcademicPeriod::class)
            ->where('auditable_id', $period->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('active', $log->metadata['changes']['status']);
        $this->assertEquals($admin->id, $log->user_id);
    }

    public function test_approving_evidence_is_recorded_as_a_review_creation(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

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

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);
        $version = $evidence->startOrGetDraftVersion($teacher);
        $evidence->submitCurrentVersion();

        Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence])->call('approve');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Review::class,
            'user_id' => $leader->id,
        ]);
    }

    public function test_only_administrator_can_view_the_audit_log(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($coordination)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk();
    }

    public function test_audit_log_screen_shows_translated_labels_instead_of_raw_code_values(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);

        AuditLog::create([
            'action' => 'login',
            'user_id' => $teacher->id,
            'auditable_type' => User::class,
            'auditable_id' => $teacher->id,
            'created_at' => now(),
        ]);

        $teacher->update(['is_active' => false]);

        $response = $this->actingAs($admin)->get('/admin/audit-logs');

        $response->assertOk();
        $response->assertSee('Inició sesión');
        $response->assertSee('Registro modificado');
        $response->assertSee('Cuenta desactivada');
        $response->assertDontSee('is_active');
        $response->assertDontSee('"changes"');
    }
}
