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
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    /**
     * Mismo encabezado que csv(), con la columna opcional
     * tipo_vinculacion agregada al final — separado de csv() para no
     * tocar ninguna de las pruebas existentes (que ya prueban que esta
     * columna, al no estar presente, nunca bloquea el archivo).
     */
    private function csvWithEmploymentType(array $rows): UploadedFile
    {
        $header = 'tipo_documento,numero_documento,nombre_completo,correo_institucional,codigo_programa,roles,estado,tipo_vinculacion';
        $lines = array_map(fn (array $r) => implode(',', $r), $rows);

        return UploadedFile::fake()->createWithContent('docentes.csv', implode("\n", [$header, ...$lines]));
    }

    public function test_teacher_cannot_access_the_import_wizard(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/users/import')->assertForbidden();
    }

    /**
     * Revisión de la directora, punto 8: el asistente pasa de ser de
     * Administración a ser exclusivo de Coordinación.
     */
    public function test_coordination_can_access_the_import_wizard(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/users/import')->assertOk();
    }

    public function test_administrator_cannot_access_the_import_wizard(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/admin/users/import')->assertForbidden();
    }

    public function test_uploading_a_file_with_missing_columns_shows_an_error_without_writing_anything(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $file = UploadedFile::fake()->createWithContent('malo.csv', "nombre,correo\nJuan,juan@uts.edu.co");

        Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->assertSet('step', 'upload');

        $this->assertDatabaseCount('users', 1); // solo la coordinadora de la prueba
    }

    public function test_preview_shows_mixed_valid_and_invalid_rows_without_writing_to_the_database(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
        ]);

        $component = Livewire::actingAs($coordination)
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
        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        $html = Livewire::actingAs($coordination)
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

        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
            ['CC', '', '', 'tampoco-es-un-correo', '', '', ''],
        ]);

        $component = Livewire::actingAs($coordination)
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

    /**
     * Revisión de la directora: tipo_vinculacion es una columna opcional
     * de esta misma plantilla — se agrega al final, el resto de columnas
     * no cambia.
     */
    public function test_a_valid_employment_type_is_saved_on_create(): void
    {
        Notification::fake();

        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csvWithEmploymentType([
            ['CC', '1', 'Docente Con Vinculación', 'convinculacion@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo', 'Medio tiempo 1M'],
        ]);

        Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm')
            ->assertSet('summary.created', 1)
            ->assertSet('summary.rejected', 0);

        $this->assertSame('medio_tiempo_1m', User::where('email', 'convinculacion@uts.edu.co')->first()->employment_type);
    }

    public function test_an_invalid_employment_type_is_rejected_with_a_clear_message(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csvWithEmploymentType([
            ['CC', '1', 'Docente Con Vinculación Mala', 'vinculacionmala@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo', 'Cuarto de tiempo'],
        ]);

        $rows = Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertStringContainsString('El tipo de vinculación "Cuarto de tiempo" no es válido', $rows[0]['errors'][0]);
        $this->assertDatabaseMissing('users', ['email' => 'vinculacionmala@uts.edu.co']);
    }

    /**
     * "En una actualización, dejarla vacía NUNCA borra el valor que esa
     * persona ya tenía" — reimportar sin tipo_vinculacion (o con la
     * columna vacía) no debe tocar el valor que el docente ya tenía.
     */
    public function test_leaving_employment_type_blank_on_an_update_never_erases_the_existing_value(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $existing = User::factory()->create([
            'document_number' => '1',
            'name' => 'Docente Ya Vinculado',
            'email' => 'yavinculado@uts.edu.co',
            'is_active' => true,
            'employment_type' => 'planta',
        ]);

        // Mismo archivo SIN la columna tipo_vinculacion (ver csv(), no
        // csvWithEmploymentType()) — la fila sí cambia el nombre, así que
        // de todos modos resuelve a "update", no a "skip".
        $file = $this->csv([
            ['CC', '1', 'Nombre Actualizado', 'yavinculado@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm')
            ->assertSet('summary.updated', 1);

        $this->assertSame('planta', $existing->fresh()->employment_type);
    }

    public function test_a_row_matching_an_existing_user_updates_it_instead_of_creating_a_duplicate(): void
    {
        Notification::fake();

        $coordination = $this->userWithRole(RoleName::Coordination);
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

        $component = Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $component->assertSet('summary.created', 0);
        $component->assertSet('summary.updated', 1);

        $this->assertDatabaseCount('users', 2); // coordinación + el mismo docente, no 3
        $existing->refresh();
        $this->assertSame('Nombre Actualizado', $existing->name);
        $this->assertSame($programUnit->id, $existing->program_unit_id);
        $this->assertTrue($existing->hasRole(RoleName::Teacher));

        // Un usuario ya existente nunca recibe el correo de activación de cuenta nueva.
        Notification::assertNotSentTo($existing, ResetPasswordNotification::class);
    }

    /**
     * Reporte de la directora, punto 1: "al subir dos veces un archivo
     * idéntico, la vista previa dice «Sin cambios» pero el resumen
     * final cuenta la cuenta como «actualizada»" — reproducido tal cual,
     * subiendo e importando el mismo archivo dos veces seguidas.
     */
    public function test_uploading_the_same_file_twice_shows_no_changes_both_in_preview_and_in_the_final_summary(): void
    {
        Notification::fake();

        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = fn () => $this->csv([
            ['CC', '1', 'Ana María Pérez', 'ana.perez@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        // Primera subida: crea al docente.
        Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file())
            ->call('preview')
            ->call('confirm')
            ->assertSet('summary.created', 1)
            ->assertSet('summary.updated', 0)
            ->assertSet('summary.skipped', 0);

        // Segunda subida del mismo archivo, sin ningún cambio real.
        $second = Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file())
            ->call('preview');

        // Vista previa: la fila se resuelve como "Sin cambios".
        $second->assertSet('rows.0.action', 'skip');

        $second->call('confirm')
            ->assertSet('summary.created', 0)
            ->assertSet('summary.updated', 0)
            ->assertSet('summary.skipped', 1)
            ->assertSet('summary.rejected', 0);

        // coordinación + el docente, nunca un segundo usuario ni una fila rechazada.
        $this->assertDatabaseCount('users', 2);
    }

    public function test_records_an_audit_log_entry_with_the_import_summary(): void
    {
        Notification::fake();

        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $log = AuditLog::where('action', 'teachers_bulk_imported')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($coordination->id, $log->user_id);
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
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="preview"', false)
            ->assertSee('Procesando...');
    }

    public function test_the_confirm_button_disables_itself_and_shows_a_loading_state_while_importing(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $file = $this->csv([
            ['CC', '1', 'Docente Válido', 'valido@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($coordination)
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

        $coordination = $this->userWithRole(RoleName::Coordination);

        $file = $this->csv([
            ['CC', '1', 'Docente Programa Malo', 'invalido@uts.edu.co', 'Programa Que No Existe', 'Docente', 'Activo'],
        ]);

        $component = Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm');

        $component->assertSet('summary.rejected', 1);

        $response = $component->call('downloadRejected');

        $response->assertFileDownloaded();
    }

    /**
     * Cuadro de seguimiento, punto 14: "procesar un lote mixto (filas
     * válidas, inválidas y repetidas) y comparar el resumen en pantalla,
     * el evento de auditoría y el reporte de rechazados (Excel): mismos
     * conteos, motivos claros por fila y mismo orden". Reproducción con
     * las 4 categorías en el mismo archivo: una fila válida nueva, una
     * fila idéntica a un docente ya existente (omitida, no "actualizada"
     * — punto 12), dos filas con el mismo documento (repetidas dentro
     * del archivo) y una con correo inválido.
     */
    public function test_a_mixed_batch_reports_the_same_counts_and_order_on_screen_in_the_audit_log_and_in_the_rejected_excel(): void
    {
        Notification::fake();

        $coordination = $this->userWithRole(RoleName::Coordination);
        $programUnit = ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $existing = User::factory()->create([
            'name' => 'Docente Existente',
            'document_number' => '999',
            'document_type' => 'CC',
            'email' => 'existente@uts.edu.co',
            'program_unit_id' => $programUnit->id,
            'is_active' => true,
        ]);
        $existing->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        $file = $this->csv([
            // Fila 2: válida, nueva -> created.
            ['CC', '1', 'Docente Nuevo', 'nuevo@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            // Fila 3: idéntica al docente ya existente -> skip (punto 12).
            ['CC', '999', 'Docente Existente', 'existente@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            // Filas 4 y 5: mismo documento entre sí -> ambas rejected.
            ['CC', '2', 'Docente Repetido Uno', 'repetido.uno@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            ['CC', '2', 'Docente Repetido Dos', 'repetido.dos@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
            // Fila 6: correo inválido -> rejected.
            ['CC', '3', 'Docente Correo Malo', 'esto-no-es-un-correo', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        $component = Livewire::actingAs($coordination)
            ->test(TeacherImportWizard::class)
            ->set('file', $file)
            ->call('preview');

        // Vista previa: cada fila resuelve a la acción esperada, en el
        // mismo orden del archivo (filas 2 a 6).
        $rows = $component->get('rows');
        $this->assertSame([2, 3, 4, 5, 6], array_column($rows, 'row_number'));
        $this->assertSame(['create', 'skip', 'reject', 'reject', 'reject'], array_column($rows, 'action'));

        $component->call('confirm');

        // Resumen en pantalla.
        $component->assertSet('summary.created', 1)
            ->assertSet('summary.updated', 0)
            ->assertSet('summary.skipped', 1)
            ->assertSet('summary.rejected', 3);

        // Evento de auditoría: mismos conteos que el resumen en pantalla.
        $log = AuditLog::where('action', 'teachers_bulk_imported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(5, $log->metadata['processed']);
        $this->assertSame(1, $log->metadata['created']);
        $this->assertSame(0, $log->metadata['updated']);
        $this->assertSame(1, $log->metadata['skipped']);
        $this->assertSame(3, $log->metadata['rejected']);

        // Reporte de rechazados (Excel): mismas 3 filas, mismo orden
        // (filas 4, 5 y 6 del archivo) y un motivo claro y específico por
        // fila — nunca un mensaje genérico compartido entre todas.
        $download = $component->call('downloadRejected');
        $download->assertFileDownloaded('docentes-rechazados.xlsx');

        $content = base64_decode(data_get($download->effects, 'download.content'));
        $tmpPath = tempnam(sys_get_temp_dir(), 'rechazados').'.xlsx';
        file_put_contents($tmpPath, $content);

        $sheet = IOFactory::load($tmpPath)->getActiveSheet();
        $exportedRows = $sheet->toArray(null, true, true, false);
        unset($exportedRows[0]); // encabezados

        $exportedRows = array_values($exportedRows);

        $this->assertCount(3, $exportedRows);
        // Columnas: fila, tipo_documento, numero_documento, nombre_completo,
        // correo_institucional, codigo_programa, roles, estado,
        // tipo_vinculacion, motivo_rechazo (índice 9).
        $this->assertSame(['4', '5', '6'], array_column($exportedRows, 0));
        $this->assertStringContainsString('duplicado dentro del archivo', $exportedRows[0][9]);
        $this->assertStringContainsString('duplicado dentro del archivo', $exportedRows[1][9]);
        $this->assertStringContainsString('correo electrónico válida', $exportedRows[2][9]);

        unlink($tmpPath);
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

        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Docente Nuevo', 'docente.nuevo@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        Livewire::actingAs($coordination)
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
