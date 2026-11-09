<?php

namespace Tests\Feature\Distribution;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Distribution\AssignmentIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora, punto 7: columna «Vinculación» en Distribución
 * docente. La tabla lista cada asignación individual (una fila por
 * docente+actividad, sin agrupar ni totalizar por docente — ver
 * AssignmentIndex::render(), orderByDesc('created_at')) y el filtro de
 * periodo admite "todos los periodos", donde un tope SEMANAL no tiene un
 * valor único que comparar; por eso esta columna solo muestra la
 * etiqueta de vinculación (o "Sin definir"), nunca una razón horas/tope —
 * ver el hallazgo explícito en el mensaje de entrega de este lote.
 */
class AssignmentIndexEmploymentTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $attributes));
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    private function coordination(): User
    {
        return $this->userWithRole(RoleName::Coordination);
    }

    public function test_the_column_shows_the_employment_type_label(): void
    {
        $coordination = $this->coordination();
        $teacher = $this->userWithRole(RoleName::Teacher, ['employment_type' => 'medio_tiempo_4m']);
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active])->id,
        ]);

        Livewire::actingAs($coordination)
            ->test(AssignmentIndex::class)
            ->set('periodFilter', null)
            ->assertSee('Medio tiempo 4M');
    }

    public function test_the_column_shows_sin_definir_when_the_teacher_has_no_employment_type(): void
    {
        $coordination = $this->coordination();
        $teacher = $this->userWithRole(RoleName::Teacher);
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active])->id,
        ]);

        Livewire::actingAs($coordination)
            ->test(AssignmentIndex::class)
            ->set('periodFilter', null)
            ->assertSee('Sin definir');
    }

    /**
     * El filtro de periodo sigue funcionando exactamente igual: la nueva
     * columna no cambia qué filas entran ni cómo se ordenan (sigue por
     * fecha de creación descendente — esta tabla nunca estuvo ordenada
     * alfabéticamente).
     */
    public function test_the_period_filter_still_scopes_the_results(): void
    {
        $coordination = $this->coordination();
        $teacher = $this->userWithRole(RoleName::Teacher, ['employment_type' => 'planta']);
        $periodA = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $periodB = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $periodA->id,
            'activity_id' => Activity::factory()->create()->id,
        ]);
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $periodB->id,
            'activity_id' => Activity::factory()->create()->id,
        ]);

        Livewire::actingAs($coordination)
            ->test(AssignmentIndex::class)
            ->set('periodFilter', $periodA->id)
            ->assertViewHas('assignments', fn ($assignments) => $assignments->total() === 1);
    }
}
