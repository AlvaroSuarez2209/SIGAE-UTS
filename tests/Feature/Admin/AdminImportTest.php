<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\AdminImportWizard;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
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

    private function csv(array $rows): UploadedFile
    {
        $header = 'nombre_completo,correo_institucional,roles,estado';
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

        $file = $this->csv([
            ['Nueva Coordinadora', 'nueva.coord@sigae.local', 'Coordinación', 'Activo'],
            ['Nuevo Administrador', 'nuevo.admin@sigae.local', 'Administrador', 'Activo'],
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

        $file = $this->csv([
            ['Intento Docente', 'intento.docente@sigae.local', 'Docente', 'Activo'],
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

        $file = $this->csv([
            ['Intento Auditor', 'intento.auditor@sigae.local', 'Auditor', 'Activo'],
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(AdminImportWizard::class)
            ->set('file', $file)
            ->call('preview')
            ->get('rows');

        $this->assertSame('reject', $rows[0]['action']);
        $this->assertDatabaseMissing('users', ['email' => 'intento.auditor@sigae.local']);
    }
}
