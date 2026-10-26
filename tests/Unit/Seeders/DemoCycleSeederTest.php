<?php

namespace Tests\Unit\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoCycleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DemoCycleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function demoTeacher(): User
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());
        config(['seeding.demo_cycle_teacher_email' => $teacher->email]);

        return $teacher;
    }

    public function test_fails_with_a_clear_message_when_the_teacher_email_is_missing(): void
    {
        config(['seeding.demo_cycle_teacher_email' => null]);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_CYCLE_TEACHER_EMAIL');

        (new DemoCycleSeeder)->run();
    }

    public function test_fails_with_a_clear_message_when_the_configured_teacher_does_not_exist(): void
    {
        config(['seeding.demo_cycle_teacher_email' => 'no-existe@sigae-uts.local']);
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_CYCLE_TEACHER_EMAIL');

        (new DemoCycleSeeder)->run();
    }

    public function test_fails_with_a_clear_message_when_there_is_no_active_academic_period(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);

        $this->expectException(RuntimeException::class);

        (new DemoCycleSeeder)->run();
    }

    public function test_creates_the_six_demo_deliverables_with_the_expected_states(): void
    {
        $teacher = $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $deliverables = Deliverable::where('name', 'like', '[DEMO] %')->get()->keyBy('name');
        $this->assertCount(6, $deliverables);

        $pending = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Pendiente sin carga'))->first();
        $this->assertEquals(EvidenceStatus::Pending, $pending->status);
        $this->assertNull($pending->current_version_id);

        $draft = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Borrador en progreso'))->first();
        $this->assertEquals(EvidenceStatus::Draft, $draft->status);
        $this->assertCount(1, $draft->currentVersion->files);

        $returned = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Devuelto, listo para reenviar'))->first();
        $this->assertEquals(EvidenceStatus::Draft, $returned->status); // segunda versión, en edición
        $this->assertCount(2, $returned->versions);
        $this->assertCount(1, $returned->reviews);
        $this->assertEquals('returned', $returned->reviews->first()->decision->value);
        $this->assertNotEmpty($returned->reviews->first()->observations);

        // Se deja en Pendiente a propósito (no se simula
        // evidences:mark-overdue) — así, al intentar enviarla, llega al
        // chequeo de closes_at en vez de que isEditable() la rechace
        // antes por "Vencido" (ver test dedicado más abajo).
        $overdue = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Vencido'))->first();
        $this->assertEquals(EvidenceStatus::Pending, $overdue->status);

        $exempt = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Exento con justificación'))->first();
        $this->assertEquals(EvidenceStatus::Exempt, $exempt->status);
        $this->assertNotEmpty($exempt->exemption_reason);

        $optional = Deliverable::where('name', '[DEMO] Opcional de ejemplo')->first();
        $this->assertFalse($optional->is_mandatory);

        $mandatoryWithWeight = Deliverable::where('name', '[DEMO] Pendiente sin carga')->first();
        $this->assertTrue($mandatoryWithWeight->is_mandatory);
        $this->assertEquals(25, $mandatoryWithWeight->weight_percentage);

        // closes_at por caso, tal como se pidió explícitamente.
        $pastClose = ['[DEMO] Devuelto, listo para reenviar', '[DEMO] Vencido'];
        foreach ($deliverables as $name => $deliverable) {
            $this->assertNotNull($deliverable->closes_at, "{$name} debería tener closes_at configurado.");

            if (in_array($name, $pastClose, true)) {
                $this->assertTrue($deliverable->closes_at->isPast(), "{$name} debería tener closes_at en el pasado.");
            } else {
                $this->assertTrue($deliverable->closes_at->isFuture(), "{$name} debería tener closes_at en el futuro.");
            }
        }

        // due_at: todas en el futuro salvo las dos con closes_at pasado
        // (coherencia interna: closes_at nunca antes de due_at).
        foreach ($deliverables as $name => $deliverable) {
            if (in_array($name, $pastClose, true)) {
                $this->assertTrue($deliverable->due_at->isPast(), "{$name} debería tener due_at en el pasado.");

                continue;
            }
            $this->assertTrue($deliverable->due_at->isFuture(), "{$name} debería tener due_at en el futuro.");
        }

        $leader = User::where('email', DemoCycleSeeder::DEMO_LEADER_EMAIL)->first();
        $this->assertNotNull($leader);
        $this->assertTrue($leader->hasRole(RoleName::Leader));
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();
        (new DemoCycleSeeder)->run();

        $this->assertCount(6, Deliverable::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, User::where('email', DemoCycleSeeder::DEMO_LEADER_EMAIL)->get());
        $this->assertCount(1, Activity::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, Component::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, ProgramUnit::where('name', 'like', '[DEMO] %')->get());

        // La versión devuelta no debe duplicar su historial al reintentar.
        $returned = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Devuelto, listo para reenviar'))->first();
        $this->assertCount(2, $returned->versions);
        $this->assertCount(1, $returned->reviews);
    }

    public function test_does_not_touch_deliverables_the_teacher_already_had(): void
    {
        $teacher = $this->demoTeacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $alreadyReviewed = Deliverable::factory()->create([
            'name' => 'Informe de seguimiento académico',
            'academic_period_id' => $period->id,
        ]);
        $alreadyReviewed->recipients()->attach($teacher->id);
        $existingEvidence = Evidence::factory()->create([
            'deliverable_id' => $alreadyReviewed->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Approved,
        ]);

        (new DemoCycleSeeder)->run();

        $alreadyReviewed->refresh();
        $existingEvidence->refresh();
        $this->assertEquals('Informe de seguimiento académico', $alreadyReviewed->name);
        $this->assertEquals(EvidenceStatus::Approved, $existingEvidence->status);
        $this->assertFalse(str($alreadyReviewed->name)->startsWith('[DEMO]'));
    }

    public function test_refuses_to_run_in_production_without_force(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $this->app['env'] = 'production';

        (new DemoCycleSeeder)->run();

        $this->assertSame(0, Deliverable::where('name', 'like', '[DEMO] %')->count());
    }

    /**
     * El --force real solo se puede ejercitar a través del comando
     * db:seed (quien de verdad registra esa opción) — instanciar el
     * seeder a mano, como el resto de pruebas de esta clase, nunca pasa
     * por $this->command, así que esa ruta nunca podría probarse ahí.
     */
    public function test_force_option_allows_it_to_run_in_production_via_db_seed(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DemoCycleSeeder::class, '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(6, Deliverable::where('name', 'like', '[DEMO] %')->count());
    }

    /**
     * (d): bloquea. La evidencia queda Pendiente con closes_at ya
     * pasado y ninguna devolución registrada — EvidenceWorkspace::submit()
     * debe rechazarla con el mensaje exacto, sin crear ninguna versión.
     */
    public function test_demo_case_d_overdue_blocks_submission_with_the_closing_date_message(): void
    {
        $teacher = $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        (new DemoCycleSeeder)->run();

        $evidence = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Vencido'))->first();

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('intento.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', 'El plazo de este entregable cerró el '.$evidence->deliverable->closes_at->toReadable().'.');

        $this->assertNotEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * (c): permite. La evidencia ya tiene una devolución registrada y
     * closes_at pasado — debe poder reenviarse igual, por la excepción
     * explícita de EvidenceWorkspace::submit().
     */
    public function test_demo_case_c_returned_allows_resubmission_despite_the_past_closing_date(): void
    {
        $teacher = $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        (new DemoCycleSeeder)->run();

        $evidence = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Devuelto, listo para reenviar'))->first();
        $this->assertTrue($evidence->deliverable->closes_at->isPast());

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('version-2-ejemplo.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', '');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }
}
