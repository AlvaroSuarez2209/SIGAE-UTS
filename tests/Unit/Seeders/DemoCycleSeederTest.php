<?php

namespace Tests\Unit\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoCycleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
        $this->assertCount(17, $deliverables);

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

        // closes_at por caso, tal como se pidió explícitamente. El
        // entregable del periodo "[DEMO]" ya cerrado (punto 7b) también
        // tiene fechas en el pasado a propósito, igual que los dos de
        // arriba.
        $pastClose = ['[DEMO] Devuelto, listo para reenviar', '[DEMO] Vencido', '[DEMO] Entregable del periodo cerrado'];
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

        $this->assertCount(17, Deliverable::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, User::where('email', DemoCycleSeeder::DEMO_LEADER_EMAIL)->get());
        $this->assertCount(1, User::where('email', DemoCycleSeeder::DEMO_LEADER_B_EMAIL)->get());
        $this->assertCount(1, User::where('email', DemoCycleSeeder::DEMO_LEADER_C_EMAIL)->get());
        $this->assertCount(5, Activity::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, Component::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(5, ProgramUnit::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, CrossCuttingCommitment::where('name', 'like', '[DEMO] %')->get());
        $this->assertCount(1, AcademicPeriod::where('name', 'like', '[DEMO] %')->get());
        // 2 en el periodo Activo (líder A y B) + 1 más del líder A en el
        // periodo "[DEMO] Periodo cerrado" (punto 7b, misma actividad).
        $this->assertCount(3, Leadership::where('activity_id', Activity::where('name', '[DEMO] Ciclo de demostración')->first()->id)->get());

        // La versión devuelta no debe duplicar su historial al reintentar.
        $returned = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Devuelto, listo para reenviar'))->first();
        $this->assertCount(2, $returned->versions);
        $this->assertCount(1, $returned->reviews);
    }

    /**
     * Punto 10: lider.demo.b queda como SEGUNDO líder vigente de la misma
     * actividad original (junto a lider.demo) — mismo ámbito, dos líderes.
     */
    public function test_leader_b_is_a_second_leader_over_the_original_activity(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $activity = Activity::where('name', '[DEMO] Ciclo de demostración')->first();
        $leaderB = User::where('email', DemoCycleSeeder::DEMO_LEADER_B_EMAIL)->first();

        $this->assertNotNull($leaderB);
        $this->assertTrue($leaderB->hasRole(RoleName::Leader));
        $this->assertTrue(
            Leadership::where('user_id', $leaderB->id)->where('activity_id', $activity->id)->exists()
        );
    }

    /**
     * Punto 10: lider.demo.c tiene su propio ámbito exclusivo (otra
     * actividad/programa "[DEMO]"), distinto del de A y B.
     */
    public function test_leader_c_has_its_own_exclusive_scope(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $activityC = Activity::where('name', '[DEMO] Actividad de Líder C')->first();
        $leaderC = User::where('email', DemoCycleSeeder::DEMO_LEADER_C_EMAIL)->first();

        $this->assertNotNull($activityC);
        $this->assertNotNull($leaderC);
        $this->assertTrue($leaderC->hasRole(RoleName::Leader));
        $this->assertTrue(
            Leadership::where('user_id', $leaderC->id)->where('activity_id', $activityC->id)->exists()
        );

        // Ningún otro líder cubre esta actividad.
        $this->assertFalse(
            Leadership::where('activity_id', $activityC->id)
                ->whereNot('user_id', $leaderC->id)
                ->exists()
        );
    }

    /**
     * Punto 7/10: la actividad "[DEMO] Actividad sin líder" no tiene
     * ningún Leadership — demuestra el respaldo de Administrador.
     */
    public function test_the_orphan_activity_has_no_leadership_at_all(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $orphanActivity = Activity::where('name', '[DEMO] Actividad sin líder')->first();

        $this->assertNotNull($orphanActivity);
        $this->assertFalse(Leadership::where('activity_id', $orphanActivity->id)->exists());

        $orphanEvidence = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Sin ningún líder vigente'))->first();
        $this->assertEquals(EvidenceStatus::Submitted, $orphanEvidence->status);
    }

    /**
     * Punto 10: una evidencia exenta en el ámbito de cada líder nuevo.
     */
    public function test_each_new_leader_has_one_exempt_evidence_in_their_own_scope(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $exemptB = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Exenta en ámbito de Líder B'))->first();
        $exemptC = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Exenta en ámbito de Líder C'))->first();

        $this->assertEquals(EvidenceStatus::Exempt, $exemptB->status);
        $this->assertEquals(EvidenceStatus::Exempt, $exemptC->status);

        $leaderB = User::where('email', DemoCycleSeeder::DEMO_LEADER_B_EMAIL)->first();
        $this->assertFalse($exemptC->isReviewableBy($leaderB));
    }

    /**
     * Punto 10: compromiso transversal "[DEMO]" con su propio entregable
     * y evidencia — ya cubierto por isCrossCutting(), solo lo hace
     * visible también en el recorrido de demostración.
     */
    public function test_creates_a_cross_cutting_commitment_with_its_own_deliverable(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $commitment = CrossCuttingCommitment::where('name', '[DEMO] Compromiso transversal')->first();
        $this->assertNotNull($commitment);

        $deliverable = Deliverable::where('name', '[DEMO] Entregable transversal')->first();
        $this->assertNotNull($deliverable);
        $this->assertTrue($deliverable->isCrossCutting());
        $this->assertEquals($commitment->id, $deliverable->cross_cutting_commitment_id);

        // Punto 1: ENVIADA, no solo Pendiente — para que aparezca de una
        // vez en la bandeja de Coordinación/Administrador.
        $evidence = Evidence::where('deliverable_id', $deliverable->id)->first();
        $this->assertEquals(EvidenceStatus::Submitted, $evidence->status);
    }

    /**
     * Punto 1 de la revisión de perfil Coordinación: las dos evidencias
     * ENVIADAS del seeder (actividad sin líder, compromiso transversal)
     * deben aparecer de una vez en su bandeja real, no solo tener el
     * status correcto en la base.
     */
    public function test_coordination_sees_the_orphan_and_cross_cutting_demo_evidences_in_their_inbox(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        (new DemoCycleSeeder)->run();

        $coordination = User::factory()->create(['is_active' => true]);
        $coordination->roles()->attach(Role::where('name', RoleName::Coordination->value)->first());

        $pendingNames = Livewire::actingAs($coordination)
            ->test(\App\Livewire\Reviews\ReviewInbox::class)
            ->viewData('pending')
            ->map(fn (Evidence $e) => $e->deliverable->name);

        $this->assertContains('[DEMO] Sin ningún líder vigente', $pendingNames);
        $this->assertContains('[DEMO] Entregable transversal', $pendingNames);
    }

    /**
     * Las contraseñas de B y C se leen de variables de entorno propias,
     * nunca hardcodeadas — mismo criterio que el líder original.
     */
    public function test_leaders_b_and_c_passwords_come_from_their_own_env_vars(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        config(['seeding.demo_cycle_leader_b_password' => 'clave-b-de-prueba']);
        config(['seeding.demo_cycle_leader_c_password' => 'clave-c-de-prueba']);

        (new DemoCycleSeeder)->run();

        $leaderB = User::where('email', DemoCycleSeeder::DEMO_LEADER_B_EMAIL)->first();
        $leaderC = User::where('email', DemoCycleSeeder::DEMO_LEADER_C_EMAIL)->first();

        $this->assertTrue(Hash::check('clave-b-de-prueba', $leaderB->password));
        $this->assertTrue(Hash::check('clave-c-de-prueba', $leaderC->password));
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

        $this->assertSame(17, Deliverable::where('name', 'like', '[DEMO] %')->count());
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

    /**
     * Punto 7(a): el conjunto obligatorio mixto (60% + 20% + sin peso) se
     * calcula por conteo simple, no por promedio ponderado — basta con
     * que UNO no tenga peso para que ComplianceCalculator ignore los
     * pesos de TODO el conjunto (ver app/Services/ComplianceCalculator.php).
     * Aprobados: el de 60% y el sin peso (2 de 3) = 66,7%, nunca 75%
     * (60/80 ponderado). El opcional nunca participa.
     */
    public function test_demo_case_7a_weighted_compliance_falls_back_to_simple_count_when_one_mandatory_item_has_no_weight(): void
    {
        $teacher = $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        (new DemoCycleSeeder)->run();

        $activity = Activity::where('name', '[DEMO] Actividad de ponderación combinada')->first();
        $this->assertNotNull($activity);

        $weighted60 = Deliverable::where('name', '[DEMO] Ponderado 60%, aprobado')->first();
        $weighted20 = Deliverable::where('name', '[DEMO] Ponderado 20%, en revisión')->first();
        $unweighted = Deliverable::where('name', '[DEMO] Sin ponderación, aprobado')->first();
        $optional = Deliverable::where('name', '[DEMO] Opcional en ponderación combinada')->first();

        $this->assertEquals(60, $weighted60->weight_percentage);
        $this->assertEquals(20, $weighted20->weight_percentage);
        $this->assertNull($unweighted->weight_percentage);
        $this->assertFalse($optional->is_mandatory);

        $this->assertEquals(EvidenceStatus::Approved, Evidence::where('deliverable_id', $weighted60->id)->first()->status);
        $this->assertEquals(EvidenceStatus::Submitted, Evidence::where('deliverable_id', $weighted20->id)->first()->status);
        $this->assertEquals(EvidenceStatus::Approved, Evidence::where('deliverable_id', $unweighted->id)->first()->status);

        $deliverablesInScope = Deliverable::where('activity_id', $activity->id)->get();
        $result = \App\Services\ComplianceCalculator::forUser($teacher, $deliverablesInScope);
        $this->assertEquals(66.7, $result['percentage']);
        $this->assertEquals(2, $result['approved']);
        $this->assertEquals(3, $result['total']);
    }

    /**
     * Punto 7(b): el periodo "[DEMO] Periodo cerrado" existe, está Cerrado
     * y tiene un entregable aprobado — listo para probar el histórico de
     * solo lectura. Confirma además que un nuevo intento de envío sobre
     * ese mismo entregable queda bloqueado, porque el periodo ya no está
     * Activo.
     */
    public function test_demo_case_7b_closed_period_has_an_approved_evidence_and_blocks_new_submissions(): void
    {
        $teacher = $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        (new DemoCycleSeeder)->run();

        $closedPeriod = AcademicPeriod::where('name', '[DEMO] Periodo cerrado')->first();
        $this->assertNotNull($closedPeriod);
        $this->assertEquals(AcademicPeriodStatus::Closed, $closedPeriod->status);

        $evidence = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Entregable del periodo cerrado'))->first();
        $this->assertEquals(EvidenceStatus::Approved, $evidence->status);
        $this->assertFalse($evidence->deliverable->acceptsEvidenceSubmissions());

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('intento.pdf', 50, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasErrors('newFiles');
    }

    /**
     * Punto 7(c): un liderazgo vencido (líder B) y uno vigente (líder C)
     * sobre la misma actividad "[DEMO]" — solo el vigente puede revisar
     * la evidencia pendiente.
     */
    public function test_demo_case_7c_expired_leadership_cannot_review_only_the_current_one_can(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        (new DemoCycleSeeder)->run();

        $activity = Activity::where('name', '[DEMO] Actividad de sustitución de líder')->first();
        $leaderB = User::where('email', DemoCycleSeeder::DEMO_LEADER_B_EMAIL)->first();
        $leaderC = User::where('email', DemoCycleSeeder::DEMO_LEADER_C_EMAIL)->first();

        $expired = Leadership::where('user_id', $leaderB->id)->where('activity_id', $activity->id)->first();
        $current = Leadership::where('user_id', $leaderC->id)->where('activity_id', $activity->id)->first();
        $this->assertNotNull($expired);
        $this->assertNotNull($current);
        $this->assertTrue($expired->ends_at->isPast());
        $this->assertNull($current->ends_at);

        $evidence = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Pendiente de revisión tras sustitución de líder'))->first();
        $this->assertEquals(EvidenceStatus::Submitted, $evidence->status);
        $this->assertFalse($evidence->isReviewableBy($leaderB));
        $this->assertTrue($evidence->isReviewableBy($leaderC));
    }

    /**
     * Punto 7(d): la exención sigue funcionando con normalidad dentro del
     * mismo ámbito de sustitución de líder.
     */
    public function test_demo_case_7d_exemption_works_normally_during_a_leader_substitution(): void
    {
        $this->demoTeacher();
        AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        (new DemoCycleSeeder)->run();

        $evidence = Evidence::whereHas('deliverable', fn ($q) => $q->where('name', '[DEMO] Exenta durante sustitución de líder'))->first();
        $this->assertEquals(EvidenceStatus::Exempt, $evidence->status);
        $this->assertNotEmpty($evidence->exemption_reason);
    }
}
