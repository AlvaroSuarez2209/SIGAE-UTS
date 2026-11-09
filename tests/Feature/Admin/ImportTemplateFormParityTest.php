<?php

namespace Tests\Feature\Admin;

use App\Services\AdminImport\AdminImportService;
use App\Services\TeacherImport\TeacherImportService;
use Tests\TestCase;

/**
 * Hallazgo de pruebas manuales (puntos 3 y 6): "las columnas de cada
 * plantilla deben ser las mismas que los campos de su formulario de
 * creación, excepto la contraseña." Esta prueba fija ese mapeo campo↔
 * columna para las dos plantillas (Administrador y Coordinación) contra
 * los wire:model reales de user-form.blade.php, para que una columna
 * agregada o quitada de un lado sin el otro se note de inmediato.
 *
 * employment_type es la única excepción deliberada: existe en el
 * formulario pero solo aplica a Docente/Líder, así que solo vive en la
 * plantilla de Coordinación (TeacherImportService::EMPLOYMENT_TYPE_HEADER),
 * nunca en la del Administrador.
 */
class ImportTemplateFormParityTest extends TestCase
{
    /**
     * wire:model real de user-form.blade.php => columna esperada en AMBAS
     * plantillas (password y employment_type se verifican aparte).
     */
    private const FIELD_TO_COLUMN = [
        'document_type' => 'tipo_documento',
        'document_number' => 'numero_documento',
        'name' => 'nombre_completo',
        'email' => 'correo_institucional',
        'program_unit_id' => 'codigo_programa',
        'selectedRoles' => 'roles',
        'is_active' => 'estado',
    ];

    private function userFormBlade(): string
    {
        return file_get_contents(resource_path('views/livewire/admin/users/user-form.blade.php'));
    }

    public function test_every_shared_form_field_has_a_wire_model_in_the_form(): void
    {
        $blade = $this->userFormBlade();

        foreach (array_keys(self::FIELD_TO_COLUMN) as $field) {
            $this->assertMatchesRegularExpression(
                '/wire:model(\.live)?="'.preg_quote($field, '/').'"/',
                $blade,
                "UserForm ya no tiene wire:model=\"{$field}\" — actualiza también las plantillas de importación."
            );
        }

        $this->assertStringContainsString('wire:model="employment_type"', $blade);
    }

    public function test_the_administrator_template_has_exactly_the_shared_columns_and_never_employment_type(): void
    {
        $expectedColumns = array_values(self::FIELD_TO_COLUMN);

        $this->assertSame($expectedColumns, AdminImportService::REQUIRED_HEADERS);
        $this->assertNotContains('tipo_vinculacion', AdminImportService::REQUIRED_HEADERS);
    }

    public function test_the_coordination_template_has_exactly_the_shared_columns_plus_employment_type(): void
    {
        $expectedColumns = array_values(self::FIELD_TO_COLUMN);

        $this->assertSame($expectedColumns, TeacherImportService::REQUIRED_HEADERS);
        $this->assertSame('tipo_vinculacion', TeacherImportService::EMPLOYMENT_TYPE_HEADER);
    }

    /**
     * password nunca aparece en ninguna plantilla — las cuentas creadas
     * reciben un correo para establecer la suya (ver AdminImportService::commit()
     * y TeacherImportService::commit(), Password::sendResetLink()).
     */
    public function test_password_never_appears_as_a_column_in_either_template(): void
    {
        $this->assertNotContains('password', AdminImportService::REQUIRED_HEADERS);
        $this->assertNotContains('password', TeacherImportService::REQUIRED_HEADERS);
        $this->assertNotContains('contrasena', AdminImportService::REQUIRED_HEADERS);
        $this->assertNotContains('contrasena', TeacherImportService::REQUIRED_HEADERS);
    }
}
