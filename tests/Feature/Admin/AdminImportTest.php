<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Exports\AdminImportTemplateDataSheet;
use App\Livewire\Admin\Users\AdminImportWizard;
use App\Models\AuditLog;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Revisión de la directora, punto 7: importación masiva propia del
 * Administrador, limitada a Administrador y/o Coordinación — ver
 * App\Services\AdminImport\AdminImportService.
 */
class AdminImportTest extends TestCase
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

    /**
     * Hallazgo de pruebas manuales (puntos 3 y 6): la plantilla del
     * Administrador ahora pide las mismas columnas de documento/programa
     * que UserForm — mismo encabezado que
     * TeacherImportService::REQUIRED_HEADERS, sin tipo_vinculacion.
     */
    private function csv(array $rows): UploadedFile
    {
        $header = 'tipo_documento,numero_documento,nombre_completo,correo_institucional,codigo_programa,roles,estado';
        $lines = array_map(fn (array $r) => implode(',', $r), $rows);

        return UploadedFile::fake()->createWithContent('admins.csv', implode("\n", [$header, ...$lines]));
    }

    public function test_the_users_index_offers_the_admin_import_link_to_an_administrator(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertSee('Importar administradores/coordinadores')
            ->assertSee(route('admin.users.import-admins'), false);
    }

    public function test_administrator_can_download_the_admin_import_template(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/admin/users/import-admins/template')
            ->assertOk();
    }

    public function test_coordination_cannot_access_the_admin_import_wizard(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/users/import-admins')->assertForbidden();
    }

    public function test_coordination_cannot_download_the_admin_import_template(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/users/import-admins/template')->assertForbidden();
    }

    public function test_teacher_cannot_access_the_admin_import_wizard(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/users/import-admins')->assertForbidden();
    }

    public function test_administrator_can_import_administrator_and_coordination_rows(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Notification::fake();
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Nueva Coordinadora', 'nueva.coord@sigae.local', 'Ingeniería de Sistemas', 'Coordinación', 'Activo'],
            ['CC', '2', 'Nuevo Administrador', 'nuevo.admin@sigae.local', 'Ingeniería de Sistemas', 'Administrador', 'Activo'],
        ]);

        Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->assertSet('step', 'preview')
            ->call('confirm')
            ->assertSet('step', 'result');

        $coordination = User::where('email', 'nueva.coord@sigae.local')->first();
        $this->assertNotNull($coordination);
        $this->assertTrue($coordination->hasRole(RoleName::Coordination));
        $this->assertSame('CC', $coordination->document_type);
        $this->assertSame('1', $coordination->document_number);

        $administrator = User::where('email', 'nuevo.admin@sigae.local')->first();
        $this->assertNotNull($administrator);
        $this->assertTrue($administrator->hasRole(RoleName::Administrator));

        $log = AuditLog::where('action', 'admins_bulk_imported')->first();
        $this->assertNotNull($log);
        $this->assertEquals(2, $log->metadata['created']);
    }

    /**
     * "Una fila con otro rol se rechaza con un mensaje claro por fila."
     */
    public function test_a_row_with_the_teacher_role_is_rejected_with_a_clear_message(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Intento Docente', 'intento.docente@sigae.local', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertStringContainsString('no es válido para esta importación', $rows[0]['errors'][0]);

        $this->assertDatabaseMissing('users', ['email' => 'intento.docente@sigae.local']);
    }

    public function test_a_row_with_the_auditor_role_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $file = $this->csv([
            ['CC', '1', 'Intento Auditor', 'intento.auditor@sigae.local', 'Ingeniería de Sistemas', 'Auditor', 'Activo'],
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertDatabaseMissing('users', ['email' => 'intento.auditor@sigae.local']);
    }

    /**
     * Hallazgo de pruebas manuales (puntos 3 y 6): se revierte la
     * decisión anterior de omitir tipo de documento/documento/programa en
     * la plantilla del Administrador — ahora se validan y se guardan
     * igual que en la de docentes.
     */
    public function test_a_row_with_an_invalid_document_type_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $file = $this->csv([
            ['XX', '1', 'Documento Malo', 'documentomalo@sigae.local', 'Ingeniería de Sistemas', 'Coordinación', 'Activo'],
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertDatabaseMissing('users', ['email' => 'documentomalo@sigae.local']);
    }

    public function test_a_row_with_a_nonexistent_program_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $file = $this->csv([
            ['CC', '1', 'Programa Malo', 'programamalo@sigae.local', 'Programa Que No Existe', 'Coordinación', 'Activo'],
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertStringContainsString('no existe en los programas académicos registrados', $rows[0]['errors'][0]);
    }

    /**
     * Cuadro de seguimiento, punto 13: mismo fix que
     * TeacherImportServiceTest::test_inactive_program_code_is_rejected_the_same_way_as_an_unknown_one() —
     * un programa inactivo no debe poder asignarse desde esta
     * importación, igual que ProgramUnit::active() (HasActiveScope) en
     * el resto del sistema.
     */
    public function test_a_row_with_an_inactive_program_is_rejected_the_same_way_as_a_nonexistent_one(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería Agroindustrial', 'is_active' => false]);

        $file = $this->csv([
            ['CC', '1', 'Programa Inactivo', 'programainactivo@sigae.local', 'Ingeniería Agroindustrial', 'Coordinación', 'Activo'],
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertStringContainsString('no existe en los programas académicos registrados', $rows[0]['errors'][0]);
    }

    public function test_the_downloaded_template_includes_the_document_and_program_columns(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $response = $this->actingAs($admin)->get('/admin/users/import-admins/template');

        $tmpPath = tempnam(sys_get_temp_dir(), 'plantilla').'.xlsx';
        file_put_contents($tmpPath, $response->streamedContent());

        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getSheetByName('Plantilla');

        $this->assertSame('tipo_documento', $sheet->getCell('A1')->getValue());
        $this->assertSame('numero_documento', $sheet->getCell('B1')->getValue());
        $this->assertSame('codigo_programa', $sheet->getCell('E1')->getValue());
        $this->assertSame(
            AdminImportTemplateDataSheet::EXAMPLE_PROGRAM_PLACEHOLDER,
            $sheet->getCell('E2')->getValue()
        );

        // Cuadro de seguimiento, punto 13: la hoja de instrucciones debe
        // explicar qué hacer con el marcador — mismo texto que ya trae la
        // plantilla de docentes (TeacherImportTemplateTest).
        $instructions = $spreadsheet->getSheetByName('Instrucciones');
        $this->assertNotNull($instructions);
        $instructionsText = collect(range(1, $instructions->getHighestRow()))
            ->map(fn ($row) => (string) $instructions->getCell("A{$row}")->getValue())
            ->implode(' ');
        $this->assertStringContainsString(AdminImportTemplateDataSheet::EXAMPLE_PROGRAM_PLACEHOLDER, $instructionsText);

        unlink($tmpPath);
    }

    /**
     * Cuadro de seguimiento, punto 13: "qué pasa al importar la plantilla
     * sin tocar la fila de ejemplo" — mismo comportamiento que ya prueba
     * TeacherImportTemplateTest::test_the_example_row_is_always_rejected_when_imported_unedited()
     * para la plantilla de docentes, verificado aquí end-to-end a través
     * del propio asistente (AdminImportWizard), no solo del servicio.
     */
    public function test_the_example_row_is_always_rejected_when_the_admin_template_is_imported_unedited(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $exampleRow = (new AdminImportTemplateDataSheet)->array()[0];
        $file = $this->csv([$exampleRow]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertStringContainsString('no existe en los programas académicos registrados', $rows[0]['errors'][0]);
        $this->assertDatabaseMissing('users', ['email' => $exampleRow[3]]);
    }
}
