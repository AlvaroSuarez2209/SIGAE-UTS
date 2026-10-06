<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\TeacherImportWizard;
use App\Models\AuditLog;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherImportWizardTest extends TestCase
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

    private function csv(array $rows): UploadedFile
    {
        $header = 'tipo_documento,numero_documento,nombre_completo,correo_institucional,codigo_programa,roles,estado';
        $lines = array_map(fn (array $r) => implode(',', $r), $rows);

        return UploadedFile::fake()->createWithContent('docentes.csv', implode("\n", [$header, ...$lines]));
    }

    public function test_teacher_cannot_access_the_import_wizard(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/users/import')->assertForbidden();
    }

    public function test_coordination_cannot_access_the_import_wizard(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/users/import')->assertForbidden();
    }

    public function test_uploading_a_file_with_missing_columns_shows_an_error_without_writing_anything(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = UploadedFile::fake()->createWithContent('malo.csv', "nombre,correo\nJuan,juan@uts.edu.co");

        Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->assertSet('step', 'upload');

        $this->assertDatabaseCount('users', 1); // solo el admin de la prueba
    }

    public function test_preview_shows_mixed_valid_and_invalid_rows_without_writing_to_the_database(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
        ]);

        $component = Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview');

        $component->assertSet('step', 'preview');
        $this->assertCount(2, $component->get('rows'));
        $this->assertSame([], $component->get('rows')[0]['errors']);
        $this->assertNotEmpty($component->get('rows')[1]['errors']);

        // La vista previa nunca escribe en la base de datos.
        $this->assertDatabaseMissing('users', ['email' => 'valido@uts.edu.co']);
        $this->assertDatabaseMissing('users', ['email' => 'invalido@uts.edu.co']);
    }

    /**
     * Bloque de ajustes de interfaz, punto 4: un solo clic en "Confirmar
     * importación" escribía de inmediato todo el lote — ahora pasa primero
     * por el confirm-modal compartido, mismo patrón ya usado en 8+ lugares
     * del sistema.
     */
    public function test_confirming_the_import_requires_confirmation(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        $html = Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->html();

        $this->assertStringContainsString("dispatch('confirm-modal'", $html);
        $this->assertStringContainsString('Confirmar importación', $html);
        $this->assertStringContainsString('$wire.confirm()', $html);
        $this->assertDatabaseMissing('users', ['email' => 'valido@uts.edu.co']);
    }

    public function test_confirming_a_mixed_batch_only_creates_the_valid_rows_and_reports_the_rest_as_rejected(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
            ['CC', '', '', 'tampoco-es-un-correo', '', '', ''],
        ]);

        $component = Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $component->assertSet('step', 'result');
        $component->assertSet('summary.created', 1);
        $component->assertSet('summary.updated', 0);
        $component->assertSet('summary.skipped', 0);
        $component->assertSet('summary.rejected', 2);

        $this->assertDatabaseHas('users', ['email' => 'valido@uts.edu.co', 'is_active' => true]);
        $this->assertDatabaseMissing('users', ['email' => 'invalido@uts.edu.co']);

        $created = User::where('email', 'valido@uts.edu.co')->first();
        $this->assertTrue($created->hasRole(RoleName::Teacher));
        Notification::assertSentTo($created, ResetPasswordNotification::class);
    }

    public function test_a_row_matching_an_existing_user_updates_it_instead_of_creating_a_duplicate(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $existing = User::factory()->create([
            'document_number' => '1',
            'name' => 'Nombre Viejo',
            'email' => 'docente@uts.edu.co',
            'is_active' => true,
        ]);

        $file = $this->csv([
            ['CC', '1', 'Nombre Actualizado', 'docente@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        $component = Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $component->assertSet('summary.created', 0);
        $component->assertSet('summary.updated', 1);

        $this->assertDatabaseCount('users', 2); // admin + el mismo docente, no 3
        $existing->refresh();
        $this->assertSame('Nombre Actualizado', $existing->name);
        $this->assertSame($programUnit->id, $existing->program_unit_id);
        $this->assertTrue($existing->hasRole(RoleName::Teacher));

        // Un usuario ya existente nunca recibe el correo de activación de cuenta nueva.
        Notification::assertNotSentTo($existing, ResetPasswordNotification::class);
    }

    public function test_records_an_audit_log_entry_with_the_import_summary(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $log = AuditLog::where('action', 'teachers_bulk_imported')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(1, $log->metadata['created']);
        $this->assertSame(1, $log->metadata['rejected']);
        $this->assertSame('docentes.csv', $log->metadata['file_name']);
    }

    /**
     * Diagnóstico del asistente de importación (archivos grandes pueden
     * tardar varios segundos): preview() y confirm() ya tenían
     * wire:loading/wire:target desde la Prioridad 2 original — esto solo
     * confirma con una prueba real que ese cableado sigue presente en el
     * HTML renderizado, para que un cambio futuro no lo elimine en
     * silencio. El estado real de wire:loading es puramente de cliente
     * (Alpine/Livewire JS); esto verifica únicamente los atributos.
     */
    public function test_the_preview_button_disables_itself_and_shows_a_loading_state_while_processing(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="preview"', false)
            ->assertSee('Procesando...');
    }

    public function test_the_confirm_button_disables_itself_and_shows_a_loading_state_while_importing(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="confirm"', false)
            ->assertSee('Importando...');
    }

    public function test_rejected_rows_report_can_be_downloaded_after_confirming(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(RoleName::Administrator);

        $file = $this->csv([
            ['CC', '1', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
        ]);

        $component = Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $component->assertSet('summary.rejected', 1);

        $response = $component->call('downloadRejected');

        $response->assertFileDownloaded();
    }

    /**
     * Bug real reportado tras la Prioridad 2: el correo de activación nunca
     * llegó a Mailpit. El diagnóstico confirmó que el código estaba
     * correcto — Password::sendResetLink() encolaba bien el job, pero
     * ningún `php artisan queue:work` estaba corriendo para procesarlo
     * (QUEUE_CONNECTION=database en local exige un worker aparte). Los
     * demás tests de este archivo usan Notification::fake(), que
     * intercepta ANTES de tocar la cola real — nunca habrían detectado ese
     * escenario. Este test fuerza la conexión `database` (testing usa
     * `sync` por defecto, que ejecutaría la notificación en el momento sin
     * pasar por ninguna tabla) para verificar, sin falsear nada, que:
     * 1) el job realmente llega a la tabla `jobs` con el destinatario
     *    correcto, y 2) un worker real lo procesa sin errores.
     */
    public function test_the_activation_email_is_queued_in_the_real_jobs_table_and_processes_without_error(): void
    {
        Config::set('queue.default', 'database');

        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Nuevo', 'docente.nuevo@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($admin)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $teacher = User::where('email', 'docente.nuevo@uts.edu.co')->firstOrFail();

        $job = DB::table('jobs')->first();

        $this->assertNotNull($job, 'El correo de activación no se encoló en la tabla jobs real.');
        $this->assertStringContainsString('ResetPasswordNotification', $job->payload);
        $this->assertSame(0, $job->attempts, 'El job no debería haber sido tomado todavía por ningún worker.');

        // El job solo serializa un ModelIdentifier (clase + id); al
        // deserializarlo aquí, Laravel lo resuelve de vuelta a un modelo
        // real (mismo mecanismo que usa el worker) — se compara contra el
        // id real del docente creado, no contra su correo en texto plano.
        $command = unserialize(json_decode($job->payload, true)['data']['command']);
        $this->assertSame([$teacher->id], $command->notifiables->pluck('id')->all());

        // Ahora sí, un worker real procesa la cola — reproduce exactamente
        // el "php artisan queue:work" que faltó correr durante el bug real.
        $this->artisan('queue:work', ['--stop-when-empty' => true])->assertExitCode(0);

        $this->assertSame(0, DB::table('jobs')->count(), 'El job debió procesarse y salir de la cola.');
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'El procesamiento del job no debió fallar.');
    }
}
