<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAccessTest extends TestCase
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

    public function test_teacher_and_leader_cannot_access_reports(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($teacher)->get('/reports/consolidated')->assertForbidden();
        $this->actingAs($leader)->get('/reports/consolidated')->assertForbidden();
    }

    public function test_administrator_coordination_and_auditor_can_access_reports(): void
    {
        foreach ([RoleName::Administrator, RoleName::Coordination, RoleName::Auditor] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/reports/consolidated')->assertOk();
        }
    }

    public function test_teacher_report_pdf_download_is_forbidden_for_a_teacher(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create();

        $this->actingAs($teacher)
            ->get("/reports/teacher/pdf?teacher={$teacher->id}&period={$period->id}")
            ->assertForbidden();
    }

    public function test_teacher_report_pdf_downloads_as_a_pdf_file(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create();

        $response = $this->actingAs($admin)
            ->get("/reports/teacher/pdf?teacher={$teacher->id}&period={$period->id}");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_teacher_report_file_name_uses_the_teacher_id_not_their_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacher->update(['name' => 'Diego Apellido Docente']);
        $period = AcademicPeriod::factory()->create(['name' => '2026-1']);

        $pdf = $this->actingAs($admin)
            ->get("/reports/teacher/pdf?teacher={$teacher->id}&period={$period->id}");
        $excel = $this->actingAs($admin)
            ->get("/reports/teacher/excel?teacher={$teacher->id}&period={$period->id}");

        foreach ([$pdf, $excel] as $response) {
            $disposition = strtolower($response->headers->get('content-disposition'));

            $this->assertStringContainsString("informe-individual-{$teacher->id}-2026-1", $disposition);
            $this->assertStringNotContainsString('diego', $disposition);
            $this->assertStringNotContainsString('apellido', $disposition);
            $this->assertStringNotContainsString('docente', $disposition);
        }
    }

    public function test_activity_report_file_name_still_describes_the_activity_no_personal_data_to_anonymize(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['name' => '2026-1']);
        $activity = Activity::factory()->create(['name' => 'Clases teóricas']);

        $response = $this->actingAs($admin)
            ->get("/reports/activity/pdf?activity={$activity->id}&period={$period->id}");

        $this->assertStringContainsString('clases-teoricas', $response->headers->get('content-disposition'));
    }

    public function test_consolidated_excel_downloads_as_a_spreadsheet(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();

        $response = $this->actingAs($admin)->get("/reports/consolidated/excel?period={$period->id}");

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_activity_report_excel_downloads_as_a_spreadsheet(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create();

        $response = $this->actingAs($admin)
            ->get("/reports/activity/excel?activity={$activity->id}&period={$period->id}");

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }
}
