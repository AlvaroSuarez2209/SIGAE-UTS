<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora: tipo de vinculación (planta, tiempo_completo,
 * medio_tiempo_1m, medio_tiempo_4m) — visible y editable solo para
 * Docente/Líder, nunca obligatorio, "Sin definir" cuando no tiene valor.
 */
class EmploymentTypeTest extends TestCase
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

    public function test_employment_type_field_is_visible_only_for_teacher_or_leader(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $component = Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->assertDontSee('Tipo de vinculación');

        $component->set('selectedRoles', [RoleName::Coordination->value, RoleName::Teacher->value])
            ->assertSee('Tipo de vinculación');
    }

    public function test_coordination_can_set_the_employment_type_when_creating_a_teacher(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->set('name', 'Docente Con Vinculación')
            ->set('email', 'vinculacion@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', $programUnit->id)
            ->set('employment_type', 'tiempo_completo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('tiempo_completo', User::where('email', 'vinculacion@sigae.local')->first()->employment_type);
    }

    /**
     * "Tanto Administrador como Coordinación pueden editarlo, incluso en
     * usuarios que ya existen" — Administración ya no puede tocar los
     * roles de un Docente (punto 7), pero sí su vinculación.
     */
    public function test_administrator_can_edit_the_employment_type_of_an_existing_teacher(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('employment_type', 'planta')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('planta', $teacher->fresh()->employment_type);
    }

    public function test_saving_without_an_employment_type_is_never_blocked(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->set('name', 'Docente Sin Vinculación')
            ->set('email', 'sinvinculacion@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', $programUnit->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(User::where('email', 'sinvinculacion@sigae.local')->first()->employment_type);
    }

    public function test_an_invalid_employment_type_value_is_rejected(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($coordination)
            ->test(UserForm::class)
            ->set('name', 'Docente Vinculación Mala')
            ->set('email', 'vinculacionmala@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', $programUnit->id)
            ->set('employment_type', 'cuarto_de_tiempo')
            ->call('save')
            ->assertHasErrors('employment_type');
    }

    public function test_the_user_list_shows_sin_definir_for_a_teacher_without_employment_type(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->userWithRole(RoleName::Teacher, ['name' => 'Docente Sin Definir']);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->assertSee('Sin definir');
    }

    public function test_the_user_list_shows_the_employment_type_label_when_set(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->userWithRole(RoleName::Teacher, ['name' => 'Docente De Planta', 'employment_type' => 'planta']);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->assertSee('Planta');
    }

    /**
     * Para un rol al que no le aplica (p. ej. Coordinación), la columna
     * muestra un guion, nunca la advertencia "Sin definir" — no es un
     * dato que le falte, simplemente no aplica.
     */
    public function test_the_user_list_shows_a_dash_for_a_role_that_does_not_use_employment_type(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->userWithRole(RoleName::Coordination, ['name' => 'Coordinadora Cualquiera']);

        $html = Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->assertSee('Coordinadora Cualquiera')
            ->html();

        $this->assertStringNotContainsString('Sin definir', $html);
    }
}
