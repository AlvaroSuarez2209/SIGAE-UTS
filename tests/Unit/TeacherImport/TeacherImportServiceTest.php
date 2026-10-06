<?php

namespace Tests\Unit\TeacherImport;

use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\TeacherImport\TeacherImportService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeacherImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->service = app(TeacherImportService::class);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'tipo_documento' => 'CC',
            'numero_documento' => '1000000001',
            'nombre_completo' => 'Ana María Pérez',
            'correo_institucional' => 'ana.perez@uts.edu.co',
            'codigo_programa' => 'Ingeniería de Sistemas',
            'roles' => 'Docente',
            'estado' => 'Activo',
        ], $overrides);
    }

    private function validate(array $rows): array
    {
        $collection = collect($rows)->map(fn (array $row) => collect($row));

        return $this->service->validateRows($collection);
    }

    public function test_a_fully_valid_row_is_resolved_as_create(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([$this->row()]);

        $this->assertSame([], $result[0]['errors']);
        $this->assertSame('create', $result[0]['action']);
        $this->assertSame(2, $result[0]['row_number']);
        $this->assertSame(['teacher'], $result[0]['resolved_role_names']);
        $this->assertTrue($result[0]['is_active']);
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $result = $this->validate([$this->row(['nombre_completo' => '', 'correo_institucional' => ''])]);

        $this->assertNotEmpty($result[0]['errors']);
        $this->assertSame('reject', $result[0]['action']);
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        $result = $this->validate([$this->row(['correo_institucional' => 'no-es-un-correo'])]);

        $this->assertNotEmpty($result[0]['errors']);
        $this->assertSame('reject', $result[0]['action']);
    }

    public function test_duplicate_document_within_the_same_file_rejects_both_rows(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([
            $this->row(['correo_institucional' => 'uno@uts.edu.co']),
            $this->row(['correo_institucional' => 'dos@uts.edu.co']),
        ]);

        $this->assertTrue(collect($result[0]['errors'])->contains(fn ($e) => str_contains($e, 'duplicado dentro del archivo')));
        $this->assertTrue(collect($result[1]['errors'])->contains(fn ($e) => str_contains($e, 'duplicado dentro del archivo')));
    }

    public function test_duplicate_email_within_the_same_file_rejects_both_rows(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([
            $this->row(['numero_documento' => '1']),
            $this->row(['numero_documento' => '2']),
        ]);

        $this->assertTrue(collect($result[0]['errors'])->contains(fn ($e) => str_contains($e, 'correo institucional está duplicado')));
        $this->assertTrue(collect($result[1]['errors'])->contains(fn ($e) => str_contains($e, 'correo institucional está duplicado')));
    }

    public function test_unknown_program_code_is_rejected(): void
    {
        $result = $this->validate([$this->row(['codigo_programa' => 'Programa Inexistente'])]);

        $this->assertTrue(collect($result[0]['errors'])->contains(fn ($e) => str_contains($e, 'no existe en los programas académicos')));
        $this->assertSame('reject', $result[0]['action']);
    }

    public function test_program_code_matches_regardless_of_case_or_accents(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([$this->row(['codigo_programa' => 'ingenieria de sistemas'])]);

        $this->assertSame([], $result[0]['errors']);
    }

    public function test_invalid_role_is_rejected(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([$this->row(['roles' => 'Supervisor'])]);

        $this->assertTrue(collect($result[0]['errors'])->contains(fn ($e) => str_contains($e, 'no es válido para esta importación')));
    }

    /**
     * Decisión de diseño explícita del diagnóstico: aunque "Administrador"
     * existe en la tabla roles, esta plantilla nunca debe poder crear uno —
     * un CSV no es una vía legítima para otorgar ese rol.
     */
    public function test_administrator_role_is_not_allowed_from_the_import_template(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([$this->row(['roles' => 'Administrador'])]);

        $this->assertTrue(collect($result[0]['errors'])->contains(fn ($e) => str_contains($e, 'no es válido para esta importación')));
        $this->assertSame('reject', $result[0]['action']);
    }

    public function test_combined_teacher_and_leader_roles_are_both_resolved(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([$this->row(['roles' => 'Docente, Líder'])]);

        $this->assertSame([], $result[0]['errors']);
        $this->assertEqualsCanonicalizing(['teacher', 'leader'], $result[0]['resolved_role_names']);
    }

    public function test_invalid_document_type_is_rejected(): void
    {
        $result = $this->validate([$this->row(['tipo_documento' => 'XX'])]);

        $this->assertNotEmpty($result[0]['errors']);
    }

    public function test_invalid_estado_value_is_rejected(): void
    {
        $result = $this->validate([$this->row(['estado' => 'Suspendido'])]);

        $this->assertNotEmpty($result[0]['errors']);
    }

    public function test_row_matching_an_existing_user_is_resolved_as_update(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $existing = User::factory()->create(['document_number' => '1000000001', 'name' => 'Nombre Viejo']);

        $result = $this->validate([$this->row()]);

        $this->assertSame('update', $result[0]['action']);
        $this->assertSame($existing->id, $result[0]['existing_user_id']);
    }

    public function test_row_identical_to_an_existing_user_is_resolved_as_skip(): void
    {
        $programUnit = ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $existing = User::factory()->create([
            'document_number' => '1000000001',
            'document_type' => 'CC',
            'name' => 'Ana María Pérez',
            'email' => 'ana.perez@uts.edu.co',
            'program_unit_id' => $programUnit->id,
            'is_active' => true,
        ]);
        $existing->roles()->attach(Role::where('name', 'teacher')->first());

        $result = $this->validate([$this->row()]);

        $this->assertSame('skip', $result[0]['action']);
    }

    public function test_document_and_email_pointing_to_two_different_existing_users_is_rejected(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        User::factory()->create(['document_number' => '1000000001', 'email' => 'otro-correo@uts.edu.co']);
        User::factory()->create(['document_number' => '999', 'email' => 'ana.perez@uts.edu.co']);

        $result = $this->validate([$this->row()]);

        $this->assertSame('reject', $result[0]['action']);
        $this->assertTrue(collect($result[0]['errors'])->contains(fn ($e) => str_contains($e, 'usuarios distintos')));
    }

    public function test_row_numbers_start_at_two_matching_the_real_excel_row(): void
    {
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        $result = $this->validate([
            $this->row(['numero_documento' => '1', 'correo_institucional' => 'uno@uts.edu.co']),
            $this->row(['numero_documento' => '2', 'correo_institucional' => 'dos@uts.edu.co']),
        ]);

        $this->assertSame(2, $result[0]['row_number']);
        $this->assertSame(3, $result[1]['row_number']);
    }
}
