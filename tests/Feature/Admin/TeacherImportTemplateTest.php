<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TeacherImportTemplateTest extends TestCase
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

    public function test_administrator_can_download_the_import_template(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $response = $this->actingAs($admin)->get('/admin/users/import/template');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_teacher_cannot_download_the_import_template(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/users/import/template')->assertForbidden();
    }

    /**
     * No solo que el archivo se descargue sin errores: verifica el formato
     * real pedido (encabezado en negrita, encabezado congelado, ancho de
     * columna ajustado, listas desplegables en roles/estado) abriendo el
     * .xlsx generado con PhpSpreadsheet, igual que lo abriría Excel.
     */
    public function test_the_template_file_has_the_expected_formatting(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $response = $this->actingAs($admin)->get('/admin/users/import/template');

        $tmpPath = tempnam(sys_get_temp_dir(), 'plantilla').'.xlsx';
        file_put_contents($tmpPath, $response->streamedContent());

        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getSheetByName('Plantilla');

        $this->assertNotNull($sheet);
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertSame('FFFFFF', $sheet->getStyle('A1')->getFont()->getColor()->getRGB());
        $this->assertNotEmpty($sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());

        $this->assertSame('A2', $sheet->getFreezePane());

        // ShouldAutoSize calcula y graba el ancho real de cada columna al
        // guardar el archivo (PhpSpreadsheet no deja la bandera "auto" viva
        // en el .xlsx resultante, graba el valor ya calculado) — por eso se
        // verifica el ancho resultante, no una bandera. El ancho por
        // defecto de PhpSpreadsheet para una columna sin ajustar es ~8.43;
        // "codigo_programa" (la columna que se veía cortada) debe quedar
        // visiblemente más ancha que eso.
        $this->assertGreaterThan(15, $sheet->getColumnDimension('E')->getWidth());

        $this->assertNotNull($sheet->getCell('F2')->getDataValidation());
        $this->assertStringContainsString('Docente', $sheet->getCell('F2')->getDataValidation()->getFormula1());
        $this->assertNotNull($sheet->getCell('G2')->getDataValidation());
        $this->assertStringContainsString('Activo', $sheet->getCell('G2')->getDataValidation()->getFormula1());

        unlink($tmpPath);
    }
}
