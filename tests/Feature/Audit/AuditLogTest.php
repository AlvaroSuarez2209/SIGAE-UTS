<?php

namespace Tests\Feature\Audit;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Audit\AuditLogIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Periods\PeriodIndex;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\InstitutionSettings;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Review;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogExportBuilder;
use App\Services\Audit\AuditLogPresenter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        $newUser = User::factory()->create(['name' => 'Nuevo Usuario', 'password' => 'plain-text-should-not-appear']);

        $log = AuditLog::where('action', 'created')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $newUser->id)
            ->first();

        $this->assertNotNull($log);
        // Prioridad 7: la creación ahora sí guarda el nombre (para que
        // describeChanges() arme "Usuario 'X' creado"), pero nunca la
        // contraseña — ver Auditable::bootAuditable().
        $this->assertEquals(['name' => 'Nuevo Usuario'], $log->metadata);
        $this->assertStringNotContainsString('plain-text-should-not-appear', json_encode($log->metadata));
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

    /**
     * Prioridad 7, parte 2, punto 1: la bitácora ahora es de solo lectura
     * también para Auditor (mismo criterio que ya tenía en Dashboard y
     * Reportes) — ver el grupo de rutas `role:administrator,auditor` en
     * routes/web.php. Coordinación, Líder y Docente siguen sin acceso.
     */
    public function test_administrator_and_auditor_can_view_the_audit_log(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $leader = $this->userWithRole(RoleName::Leader);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $auditor = $this->userWithRole(RoleName::Auditor);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($coordination)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($leader)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($teacher)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/audit-logs')->assertOk();
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
        $response->assertSee('Inicio de sesión');
        // Prioridad 7: una desactivación ya no se muestra como
        // "Modificación" genérica en la columna Acción de esa fila, sino
        // como "Desactivación" — ver AuditLogPresenter::specificUpdateLabel().
        // "Modificación" sí sigue apareciendo en el <select> del filtro
        // (actionOptions() no tiene un $log por fila al que referirse, y
        // filtrar sigue siendo por la columna real action='updated'), así
        // que no se verifica su ausencia total de la página.
        $response->assertSee('Desactivación');
        $response->assertSee('Cuenta desactivada');
        $response->assertDontSee('is_active');
        $response->assertDontSee('"changes"');
    }

    /**
     * Prioridad 7, punto 1: antes de este cambio, `created` no guardaba
     * metadata y el Detalle quedaba vacío. Se prueban tres modelos
     * representativos para cubrir tanto la concordancia de género
     * ("creado" vs "creada" — ver AuditLogPresenter::FEMININE_MODELS) como
     * un modelo sin relación directa con "creación de catálogo" (Usuario).
     */
    public function test_creation_description_is_specific_for_representative_models(): void
    {
        $component = Component::factory()->create(['name' => 'Autoevaluación']);
        $activity = Activity::factory()->create(['name' => 'Docencia directa']);
        $user = User::factory()->create(['name' => 'Juan Pérez']);

        $componentLog = AuditLog::where('action', 'created')->where('auditable_type', Component::class)->where('auditable_id', $component->id)->first();
        $activityLog = AuditLog::where('action', 'created')->where('auditable_type', Activity::class)->where('auditable_id', $activity->id)->first();
        $userLog = AuditLog::where('action', 'created')->where('auditable_type', User::class)->where('auditable_id', $user->id)->first();

        $this->assertEquals("Componente 'Autoevaluación' creado", AuditLogPresenter::describeChanges($componentLog));
        $this->assertEquals("Actividad 'Docencia directa' creada", AuditLogPresenter::describeChanges($activityLog));
        $this->assertEquals("Usuario 'Juan Pérez' creado", AuditLogPresenter::describeChanges($userLog));
    }

    /**
     * Prioridad 7, punto 2: la columna Acción debe mostrar una etiqueta
     * específica según lo que realmente cambió, no siempre "Modificación"
     * — probado sobre una actualización real (no un AuditLog construido a
     * mano), para confirmar que bootAuditable() guarda lo que
     * specificUpdateLabel() necesita.
     */
    public function test_action_label_is_specific_for_deactivation_and_status_change(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacher->update(['is_active' => false]);

        $deactivationLog = AuditLog::where('action', 'updated')->where('auditable_type', User::class)->where('auditable_id', $teacher->id)->latest('id')->first();

        $this->assertEquals('Desactivación', AuditLogPresenter::actionLabel($deactivationLog->action, $deactivationLog));

        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        $period->update(['status' => AcademicPeriodStatus::Active]);

        $statusChangeLog = AuditLog::where('action', 'updated')->where('auditable_type', AcademicPeriod::class)->where('auditable_id', $period->id)->latest('id')->first();

        $this->assertEquals('Cambio de estado', AuditLogPresenter::actionLabel($statusChangeLog->action, $statusChangeLog));
    }

    /**
     * Prioridad 7, punto 3: `metadata->previous` debe reflejar el valor
     * que tenía el campo ANTES del update real, capturado vía
     * `getOriginal()` en la ventana en la que Eloquent aún no ha
     * sincronizado el modelo — ver Auditable::bootAuditable().
     */
    public function test_a_real_update_captures_both_the_previous_and_new_value(): void
    {
        $component = Component::factory()->create(['name' => 'Nombre original']);

        $component->update(['name' => 'Nombre actualizado']);

        $log = AuditLog::where('action', 'updated')->where('auditable_type', Component::class)->where('auditable_id', $component->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertEquals('Nombre original', $log->metadata['previous']['name']);
        $this->assertEquals('Nombre actualizado', $log->metadata['changes']['name']);
        $this->assertEquals(
            'Nombre cambió de Nombre original a Nombre actualizado',
            AuditLogPresenter::describeChanges($log)
        );
    }

    /**
     * Revisión del diagnóstico de document_type/program_unit_id: el
     * trait Auditable captura automáticamente este cambio (no hace falta
     * tocarlo), y AuditLogPresenter debe traducir tanto el nombre del
     * campo ("document_type" -> "Tipo de documento") como sus valores
     * crudos ("CC"/"CE" -> sus etiquetas completas), vía
     * App\Enums\DocumentType.
     */
    public function test_a_document_type_change_is_audited_with_readable_labels(): void
    {
        $user = User::factory()->create(['document_type' => 'CC']);

        $user->update(['document_type' => 'CE']);

        $log = AuditLog::where('action', 'updated')->where('auditable_type', User::class)->where('auditable_id', $user->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertEquals('CC', $log->metadata['previous']['document_type']);
        $this->assertEquals('CE', $log->metadata['changes']['document_type']);
        $this->assertEquals(
            'Tipo de documento cambió de Cédula de ciudadanía a Cédula de extranjería',
            AuditLogPresenter::describeChanges($log)
        );
    }

    /**
     * Prioridad 7, parte 2, punto 2: el modal de detalle debe mostrar toda
     * la información del registro (actor, fecha/hora, acción específica,
     * objeto, IP, descripción completa) y el desglose campo por campo de
     * antes/después — no solo la frase compacta que ya se ve en la columna
     * "Detalle" de la tabla. Ver AuditLogPresenter::changeEntries().
     */
    public function test_detail_modal_shows_the_full_record_including_before_and_after_values(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->actingAs($admin);

        $component = Component::factory()->create(['name' => 'Nombre original']);
        $component->update(['name' => 'Nombre actualizado']);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_type', Component::class)
            ->where('auditable_id', $component->id)
            ->latest('id')
            ->first();

        Livewire::test(AuditLogIndex::class)
            ->call('showDetail', $log->id)
            ->assertSee('Detalle del registro')
            ->assertSee($admin->name)
            ->assertSee($log->created_at->toReadable())
            ->assertSee('Edición')
            ->assertSee('Componente: Nombre actualizado')
            ->assertSee($log->ip_address ?? '—')
            ->assertSee('Nombre cambió de Nombre original a Nombre actualizado')
            ->assertSee('Nombre original')
            ->assertSee('Nombre actualizado');
    }

    public function test_closing_the_detail_modal_hides_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $log = AuditLog::where('action', 'created')->where('auditable_type', Component::class)->where('auditable_id', $component->id)->first();

        Livewire::actingAs($admin)->test(AuditLogIndex::class)
            ->call('showDetail', $log->id)
            ->assertSee('Detalle del registro')
            ->call('closeDetail')
            ->assertDontSee('Detalle del registro');
    }

    /**
     * Prioridad 7, parte 2, punto 3: la exportación debe reflejar
     * exactamente los mismos 4 filtros (Usuario, Acción, Desde, Hasta) que
     * ya aplica la pantalla — probado directamente sobre el builder que
     * alimenta tanto el PDF como el Excel (AuditLogExportController), para
     * no depender de parsear un PDF binario.
     */
    public function test_export_builder_respects_the_active_filters(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        AuditLog::create(['action' => 'login', 'user_id' => $teacher->id, 'created_at' => now()]);
        AuditLog::create(['action' => 'login', 'user_id' => $otherTeacher->id, 'created_at' => now()]);
        AuditLog::create(['action' => 'logout', 'user_id' => $teacher->id, 'created_at' => now()]);

        $report = AuditLogExportBuilder::build(['user' => $teacher->id, 'action' => 'login']);
        $rows = $report['sections'][0]['rows'];

        $this->assertCount(1, $rows);
        $this->assertEquals($teacher->name, $rows[0][1]);
        $this->assertEquals('Inicio de sesión', $rows[0][2]);
    }

    public function test_audit_log_pdf_download_respects_filters_and_downloads_as_a_pdf_file(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);

        $response = $this->actingAs($admin)->get("/admin/audit-logs/pdf?user={$teacher->id}");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('bitacora-de-auditoria', strtolower($response->headers->get('content-disposition')));
    }

    /**
     * Prioridad 7, parte 2, punto 3: igual que ya hace el Excel de los
     * informes del módulo 9 (ver ReportAccessTest), verifica el contenido
     * real de las celdas — no solo que el archivo descargue — y además que
     * respeta el filtro de Acción (solo debe listar el login, no el
     * logout del mismo usuario).
     */
    public function test_audit_log_excel_includes_institution_identity_timestamp_and_respects_filters(): void
    {
        InstitutionSettings::current()->update(['name' => 'Institución de Prueba XYZ']);

        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);

        AuditLog::create(['action' => 'login', 'user_id' => $teacher->id, 'created_at' => now()]);
        AuditLog::create(['action' => 'logout', 'user_id' => $teacher->id, 'created_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/audit-logs/excel?action=login');
        $response->assertOk();

        $tempPath = tempnam(sys_get_temp_dir(), 'sigae-audit-excel-test').'.xlsx';
        file_put_contents($tempPath, $response->streamedContent());

        $sheet = IOFactory::load($tempPath)->getActiveSheet();
        $cells = collect($sheet->toArray())->flatten()->filter()->all();

        $this->assertStringContainsString('Institución de Prueba XYZ', (string) $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('Generado el', (string) $sheet->getCell('A3')->getValue());
        $this->assertContains('Inicio de sesión', $cells);
        $this->assertNotContains('Cierre de sesión', $cells);

        unlink($tempPath);
    }

    public function test_export_with_a_nonexistent_user_filter_still_filters_to_nothing_not_a_404(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/admin/audit-logs/excel?user=999999')
            ->assertOk();
    }
}
