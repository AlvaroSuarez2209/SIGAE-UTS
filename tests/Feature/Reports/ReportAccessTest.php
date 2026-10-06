<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\InstitutionSettings;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    /**
     * Prioridad 6, parte 2: los enlaces de drill-down pasan period+activity
     * como query params para que ActivityReport arranque ya filtrado — este
     * test confirma que el componente de verdad los lee (#[Url]), no solo
     * que el Dashboard arma el href correcto.
     */
    public function test_activity_report_reads_period_and_activity_from_the_query_string(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['name' => '2026-1']);
        $activity = Activity::factory()->create(['name' => 'Clases teóricas']);

        $this->actingAs($admin)
            ->get(route('reports.activity', ['period' => $period->id, 'activity' => $activity->id]))
            ->assertOk()
            ->assertSee('Clases teóricas');
    }

    /**
     * Mismo caso para TeacherReport (destino del drill-down de "Consolidado
     * por docente" en el Dashboard).
     */
    public function test_teacher_report_reads_period_and_teacher_from_the_query_string(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['name' => '2026-1']);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacher->update(['name' => 'Docente De Prueba']);

        $this->actingAs($admin)
            ->get(route('reports.teacher', ['period' => $period->id, 'teacher' => $teacher->id]))
            ->assertOk()
            ->assertSee('Docente De Prueba');
    }

    /**
     * Prioridad 6, punto 2: el Excel exportado quedaba sin ningún rastro de
     * la identidad institucional configurable (Prioridad 3) — solo el PDF
     * la usaba. Verifica el contenido real de la celda, no solo que el
     * archivo descargue — ver ReportTheme::styleExcelSheet().
     */
    public function test_excel_export_includes_the_configured_institution_name_in_its_header(): void
    {
        InstitutionSettings::current()->update(['name' => 'Institución de Prueba XYZ']);

        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();

        $response = $this->actingAs($admin)->get("/reports/consolidated/excel?period={$period->id}");
        $response->assertOk();

        $tempPath = tempnam(sys_get_temp_dir(), 'sigae-excel-test').'.xlsx';
        file_put_contents($tempPath, $response->streamedContent());

        $sheet = IOFactory::load($tempPath)->getActiveSheet();

        $this->assertStringContainsString('Institución de Prueba XYZ', (string) $sheet->getCell('A1')->getValue());

        unlink($tempPath);
    }

    /**
     * Prioridad 6, punto 2: el Excel no mostraba fecha/hora de generación,
     * a diferencia del PDF. Verifica el contenido real de la celda.
     */
    public function test_excel_export_includes_the_generation_timestamp(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();

        $response = $this->actingAs($admin)->get("/reports/consolidated/excel?period={$period->id}");
        $response->assertOk();

        $tempPath = tempnam(sys_get_temp_dir(), 'sigae-excel-test').'.xlsx';
        file_put_contents($tempPath, $response->streamedContent());

        $sheet = IOFactory::load($tempPath)->getActiveSheet();

        $this->assertStringContainsString('Generado el', (string) $sheet->getCell('A3')->getValue());

        unlink($tempPath);
    }

    /**
     * Prioridad 6, punto 6: cuando hay más de un filtro activo, el PDF debe
     * mostrar una sección explícita "Filtros aplicados:" en vez de dejarlo
     * solo implícito en el título — probado directamente sobre la plantilla
     * (sin pasar por DomPDF), que es lo que de verdad decide si se muestra.
     */
    public function test_pdf_template_shows_filters_summary_only_when_more_than_one_filter_is_active(): void
    {
        $report = ['title' => 'Informe de prueba', 'sections' => [], 'summary' => null];

        $htmlWithFilters = view('reports.pdf.report', $report + [
            'filtersSummary' => ['Periodo' => '2026-1', 'Estado' => 'Aprobado'],
        ])->render();

        $this->assertStringContainsString('Filtros aplicados:', $htmlWithFilters);
        $this->assertStringContainsString('Periodo: 2026-1', $htmlWithFilters);
        $this->assertStringContainsString('Estado: Aprobado', $htmlWithFilters);

        $htmlWithOnlyPeriod = view('reports.pdf.report', $report + ['filtersSummary' => null])->render();

        $this->assertStringNotContainsString('Filtros aplicados:', $htmlWithOnlyPeriod);
    }

    /**
     * Prioridad 6, punto 5: una falla real durante la generación (acá
     * simulada con un logo institucional corrupto, el mismo ejemplo que
     * pidió verificar) nunca debe llegar al usuario como una página 500
     * cruda — debe volver al informe con un mensaje claro en español.
     */
    public function test_excel_export_shows_a_friendly_error_instead_of_a_raw_500_when_generation_fails(): void
    {
        Storage::fake('institution');
        Storage::disk('institution')->put('logo-mark/corrupt.png', 'esto no es una imagen real');
        InstitutionSettings::current()->update(['logo_mark_path' => 'logo-mark/corrupt.png']);

        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();

        $response = $this->actingAs($admin)->get("/reports/consolidated/excel?period={$period->id}");

        $response->assertRedirect(route('reports.consolidated', ['period' => $period->id]));
        $this->assertSame(
            'No se pudo generar el documento. Intenta de nuevo o contacta al administrador si el problema persiste.',
            session('error')
        );

        // Bloque de ajustes de interfaz, punto 5 (parte 2): el bloque propio
        // de este informe se reemplazó por <x-flash-message />, registrado
        // en layouts/app.blade.php — confirma que el mensaje de verdad se
        // ve en la pantalla a la que redirige, no solo que quedó en sesión.
        $this->actingAs($admin)
            ->get(route('reports.consolidated', ['period' => $period->id]))
            ->assertSee('No se pudo generar el documento. Intenta de nuevo o contacta al administrador si el problema persiste.');
    }

    /**
     * Bloque de ajustes de interfaz, punto 5 (parte 2): mismo caso que el
     * de "Consolidado" de arriba, pero para los otros 3 informes cuyo
     * bloque local también se reemplazó por <x-flash-message /> — cada uno
     * es su propia vista Blade, así que hay que confirmarlo en las 4, no
     * solo en una.
     */
    public function test_teacher_excel_export_failure_message_is_visible_on_the_real_report_page(): void
    {
        Storage::fake('institution');
        Storage::disk('institution')->put('logo-mark/corrupt.png', 'esto no es una imagen real');
        InstitutionSettings::current()->update(['logo_mark_path' => 'logo-mark/corrupt.png']);

        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create();

        $this->actingAs($admin)->get("/reports/teacher/excel?teacher={$teacher->id}&period={$period->id}");

        $this->actingAs($admin)
            ->get(route('reports.teacher', ['teacher' => $teacher->id, 'period' => $period->id]))
            ->assertSee('No se pudo generar el documento. Intenta de nuevo o contacta al administrador si el problema persiste.');
    }

    public function test_activity_excel_export_failure_message_is_visible_on_the_real_report_page(): void
    {
        Storage::fake('institution');
        Storage::disk('institution')->put('logo-mark/corrupt.png', 'esto no es una imagen real');
        InstitutionSettings::current()->update(['logo_mark_path' => 'logo-mark/corrupt.png']);

        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create();

        $this->actingAs($admin)->get("/reports/activity/excel?activity={$activity->id}&period={$period->id}");

        $this->actingAs($admin)
            ->get(route('reports.activity', ['activity' => $activity->id, 'period' => $period->id]))
            ->assertSee('No se pudo generar el documento. Intenta de nuevo o contacta al administrador si el problema persiste.');
    }

    public function test_cross_cutting_excel_export_failure_message_is_visible_on_the_real_report_page(): void
    {
        Storage::fake('institution');
        Storage::disk('institution')->put('logo-mark/corrupt.png', 'esto no es una imagen real');
        InstitutionSettings::current()->update(['logo_mark_path' => 'logo-mark/corrupt.png']);

        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create();

        $this->actingAs($admin)->get("/reports/cross-cutting/excel?period={$period->id}");

        $this->actingAs($admin)
            ->get(route('reports.cross-cutting', ['period' => $period->id]))
            ->assertSee('No se pudo generar el documento. Intenta de nuevo o contacta al administrador si el problema persiste.');
    }

    /**
     * Un id inexistente en la URL sigue siendo un 404 real — el manejo de
     * errores de la generación no debe disfrazar esto como un "no se pudo
     * generar" genérico.
     */
    public function test_export_with_a_nonexistent_period_id_still_returns_a_real_404(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/reports/consolidated/excel?period=999999')
            ->assertNotFound();
    }
}
