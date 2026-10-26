<?php

namespace Tests\Feature\Console;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\EvidenceFile;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoCycleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCycleRevertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function seedDemoCycle(): User
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());
        config(['seeding.demo_cycle_teacher_email' => $teacher->email]);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        return $teacher;
    }

    public function test_does_nothing_and_succeeds_when_there_is_nothing_to_revert(): void
    {
        $this->artisan('demo:cycle-revert')->assertExitCode(0);
    }

    public function test_removes_everything_the_seeder_created_including_physical_files(): void
    {
        $this->seedDemoCycle();

        $fileIds = EvidenceFile::all()->pluck('id');
        $this->assertGreaterThan(0, $fileIds->count());
        $paths = EvidenceFile::whereIn('id', $fileIds)->pluck('disk_path');
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $this->artisan('demo:cycle-revert')->assertExitCode(0);

        $this->assertSame(0, Deliverable::where('name', 'like', '[DEMO] %')->count());
        $this->assertSame(0, Activity::where('name', 'like', '[DEMO] %')->count());
        $this->assertSame(0, Component::where('name', 'like', '[DEMO] %')->count());
        $this->assertSame(0, ProgramUnit::where('name', 'like', '[DEMO] %')->count());
        $this->assertSame(0, CrossCuttingCommitment::where('name', 'like', '[DEMO] %')->count());
        $this->assertSame(0, Leadership::count());
        $this->assertSame(0, User::where('email', DemoCycleSeeder::DEMO_LEADER_EMAIL)->count());
        $this->assertSame(0, User::where('email', DemoCycleSeeder::DEMO_LEADER_B_EMAIL)->count());
        $this->assertSame(0, User::where('email', DemoCycleSeeder::DEMO_LEADER_C_EMAIL)->count());
        $this->assertSame(0, EvidenceFile::whereIn('id', $fileIds)->count());

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_does_not_touch_the_real_teacher_account_or_their_other_deliverables(): void
    {
        $teacher = $this->seedDemoCycle();

        $period = AcademicPeriod::where('status', AcademicPeriodStatus::Active)->first();
        $unrelated = Deliverable::factory()->create([
            'name' => 'Informe de seguimiento académico',
            'academic_period_id' => $period->id,
        ]);
        $unrelated->recipients()->attach($teacher->id);

        $this->artisan('demo:cycle-revert')->assertExitCode(0);

        $this->assertTrue(User::where('id', $teacher->id)->exists());
        $this->assertTrue(Deliverable::where('id', $unrelated->id)->exists());
    }

    public function test_running_it_twice_is_safe(): void
    {
        $this->seedDemoCycle();

        $this->artisan('demo:cycle-revert')->assertExitCode(0);
        $this->artisan('demo:cycle-revert')->assertExitCode(0);

        $this->assertSame(0, Deliverable::where('name', 'like', '[DEMO] %')->count());
    }

    public function test_refuses_to_run_in_production_without_force(): void
    {
        $this->seedDemoCycle();
        $this->app['env'] = 'production';

        $this->artisan('demo:cycle-revert')->assertExitCode(1);

        $this->assertGreaterThan(0, Deliverable::where('name', 'like', '[DEMO] %')->count());
    }
}
