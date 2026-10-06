<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\InstitutionSettingsForm;
use App\Models\AuditLog;
use App\Models\InstitutionSettings;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\ReportTheme;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Storage::fake('institution');
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    // --- Autorización: solo Administrador, por policy, no solo por menú ---

    public function test_teacher_cannot_access_the_settings_screen_via_direct_url(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/settings/institution')->assertForbidden();
    }

    public function test_coordination_cannot_access_the_settings_screen_via_direct_url(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/settings/institution')->assertForbidden();
    }

    /**
     * Livewire::test() instancia el componente directamente — no pasa por
     * el middleware de ruta 'role:administrator'. Que esto siga
     * devolviendo 403 prueba que la autorización real vive en
     * InstitutionSettingsPolicy (Gate::authorize() en mount()), no solo en
     * que el enlace esté oculto en el menú o la ruta esté protegida.
     */
    public function test_a_non_administrator_cannot_mount_the_component_even_bypassing_the_route_middleware(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($teacher)
            ->test(InstitutionSettingsForm::class)
            ->assertForbidden();
    }

    public function test_administrator_can_view_the_settings_screen(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/admin/settings/institution')
            ->assertOk()
            ->assertSee(InstitutionSettings::DEFAULT_NAME);
    }

    // --- Guardado y validación de archivo, los 2 campos por separado ---

    public function test_administrator_can_update_the_name_and_both_logos_independently(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $loginLogo = UploadedFile::fake()->image('logo-login.png', 400, 120);
        $markLogo = UploadedFile::fake()->image('logo-mark.png', 200, 200);

        Livewire::actingAs($admin)
            ->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoLogin', $loginLogo)
            ->set('logoMark', $markLogo)
            ->call('save')
            ->assertHasNoErrors();

        $settings = InstitutionSettings::current();
        $this->assertSame('Universidad de Prueba', $settings->name);
        $this->assertNotNull($settings->logo_login_path);
        $this->assertNotNull($settings->logo_mark_path);
        $this->assertNotSame($settings->logo_login_path, $settings->logo_mark_path, 'Cada superficie debe guardar su propio archivo, no compartir uno.');
        $this->assertSame($admin->id, $settings->updated_by);
        Storage::disk('institution')->assertExists($settings->logo_login_path);
        Storage::disk('institution')->assertExists($settings->logo_mark_path);

        $log = AuditLog::where('auditable_type', InstitutionSettings::class)->where('action', 'updated')->latest('id')->first();
        $this->assertNotNull($log, 'El cambio debió quedar registrado en la bitácora de auditoría.');
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_can_update_only_the_login_logo_without_touching_the_mark_logo(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $markLogo = UploadedFile::fake()->image('logo-mark.png', 200, 200);

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoMark', $markLogo)
            ->call('save');

        $firstMarkPath = InstitutionSettings::current()->logo_mark_path;

        $loginLogo = UploadedFile::fake()->image('logo-login.png', 400, 120);

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoLogin', $loginLogo)
            ->call('save');

        $settings = InstitutionSettings::current();
        $this->assertNotNull($settings->logo_login_path);
        $this->assertSame($firstMarkPath, $settings->logo_mark_path, 'El logo de marca no debía cambiar al subir solo el del login.');
    }

    public function test_rejects_a_disallowed_file_format_on_either_logo_field(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf');

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoLogin', $file)
            ->call('save')
            ->assertHasErrors('logoLogin');

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoMark', $file)
            ->call('save')
            ->assertHasErrors('logoMark');

        $settings = InstitutionSettings::current();
        $this->assertNull($settings->logo_login_path);
        $this->assertNull($settings->logo_mark_path);
    }

    public function test_rejects_a_file_exceeding_the_max_size_on_either_logo_field(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $heavyFile = UploadedFile::fake()->create('logo-pesado.png', 3000, 'image/png');

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoLogin', $heavyFile)
            ->call('save')
            ->assertHasErrors('logoLogin');

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoMark', $heavyFile)
            ->call('save')
            ->assertHasErrors('logoMark');

        $settings = InstitutionSettings::current();
        $this->assertNull($settings->logo_login_path);
        $this->assertNull($settings->logo_mark_path);
    }

    public function test_name_is_required(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(InstitutionSettingsForm::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    // --- El valor configurado se refleja en las 3 superficies reales ---

    private function configureCustomInstitution(): InstitutionSettings
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $loginLogo = UploadedFile::fake()->image('logo-login.png', 400, 120);
        $markLogo = UploadedFile::fake()->image('logo-mark.png', 200, 200);

        Livewire::actingAs($admin)
            ->test(InstitutionSettingsForm::class)
            ->set('name', 'Universidad de Prueba')
            ->set('logoLogin', $loginLogo)
            ->set('logoMark', $markLogo)
            ->call('save');

        return InstitutionSettings::current()->fresh();
    }

    public function test_the_configured_name_and_login_logo_appear_on_the_login_screen(): void
    {
        $settings = $this->configureCustomInstitution();

        // configureCustomInstitution() deja la sesión autenticada como el
        // Administrador que guardó el cambio — /login es 'guest'-only, así
        // que hay que cerrar sesión antes de pedirla, igual que un visitante
        // real la vería.
        auth()->logout();

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Universidad de Prueba');
        $response->assertSee($settings->loginLogoUrl(), false);
        // El logo del login nunca debe confundirse con el de marca.
        $response->assertDontSee($settings->markLogoUrl(), false);
    }

    public function test_the_configured_name_and_mark_logo_appear_in_the_authenticated_sidebar(): void
    {
        $settings = $this->configureCustomInstitution();
        $teacher = $this->userWithRole(RoleName::Teacher);

        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Universidad de Prueba');
        $response->assertSee($settings->markLogoUrl(), false);
        // El sidebar usa el logo de marca, nunca el del login.
        $response->assertDontSee($settings->loginLogoUrl(), false);
    }

    public function test_the_configured_name_and_mark_logo_appear_in_the_pdf_report_header(): void
    {
        $settings = $this->configureCustomInstitution();

        // Misma vista que usa ReportExportController::pdf() antes de pasar
        // por dompdf — se verifica el HTML real que recibe el PDF, sin
        // tener que parsear el binario del PDF generado.
        $html = view('reports.pdf.report', [
            'title' => 'Informe de prueba',
            'sections' => [],
            'summary' => null,
        ])->render();

        $this->assertStringContainsString('Universidad de Prueba', $html);
        $this->assertSame($settings->markLogoDiskPath(), ReportTheme::logoPath());
        $this->assertStringContainsString(ReportTheme::logoPath(), $html);
    }

    /**
     * Bug real reportado: el nombre institucional se truncaba con "..." en
     * el encabezado REAL del sidebar (no en la vista previa, que antes no
     * reproducía el ancho real de 18rem y por eso no lo detectaba). Se
     * corrigió a wrap de 2 líneas (line-clamp-2) en vez de truncate.
     */
    public function test_a_long_institution_name_wraps_to_two_lines_in_the_sidebar_instead_of_being_truncated(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $longName = InstitutionSettings::DEFAULT_NAME; // 52 caracteres reales, el caso que reportó el bug.

        Livewire::actingAs($admin)->test(InstitutionSettingsForm::class)
            ->set('name', $longName)
            ->call('save');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        // El nombre completo debe estar en el HTML entero, sin "..." —
        // antes del fix, 'truncate' no cortaba el texto fuente, lo recortaba
        // visualmente con CSS, así que esta aserción ya pasaba incluso con
        // el bug; lo que de verdad lo prueba es la clase CSS usada.
        $response->assertSee($longName);
        $response->assertDontSee('truncate pl-12', false);
        $response->assertSee('line-clamp-2', false);
        // El nombre completo también debe estar disponible en el atributo
        // title, como respaldo si ni 2 líneas alcanzaran para un nombre aún
        // más largo.
        $response->assertSee('title="'.$longName.'"', false);
    }

    // --- Restablecer a valores por defecto, ambos logos a la vez ---

    public function test_reset_to_defaults_restores_the_original_name_and_removes_both_custom_logos(): void
    {
        $settings = $this->configureCustomInstitution();
        $customLoginPath = $settings->logo_login_path;
        $customMarkPath = $settings->logo_mark_path;
        $admin = User::find($settings->updated_by);

        Storage::disk('institution')->assertExists($customLoginPath);
        Storage::disk('institution')->assertExists($customMarkPath);

        Livewire::actingAs($admin)
            ->test(InstitutionSettingsForm::class)
            ->call('resetToDefaults');

        $settings = InstitutionSettings::current()->fresh();
        $this->assertSame(InstitutionSettings::DEFAULT_NAME, $settings->name);
        $this->assertNull($settings->logo_login_path);
        $this->assertNull($settings->logo_mark_path);
        Storage::disk('institution')->assertMissing($customLoginPath);
        Storage::disk('institution')->assertMissing($customMarkPath);

        // Y esa restauración se refleja de inmediato en el login, igual
        // que la personalización — cerrando sesión primero, ver nota en
        // test_the_configured_name_and_login_logo_appear_on_the_login_screen().
        auth()->logout();
        $this->get('/login')->assertSee(InstitutionSettings::DEFAULT_NAME);
    }

    public function test_only_administrator_can_reset_to_defaults(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($teacher)
            ->test(InstitutionSettingsForm::class)
            ->assertForbidden();
    }
}
