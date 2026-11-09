<?php

namespace Tests\Feature\Catalogs;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Deliverables\TemplateIndex;
use App\Livewire\Distribution\AssignmentForm;
use App\Models\Component;
use App\Models\DeliverableTemplate;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora, punto 6: todos los selectores principales
 * usan orderByAccentInsensitive (ya existía como macro, usado antes solo
 * en los filtros de informes) — "Ángela" debe quedar entre "Ana" y
 * "Beatriz", nunca al final por culpa de la tilde.
 */
class AccentInsensitiveOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Administrator->value)->first());

        return $user;
    }

    private function coordination(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Coordination->value)->first());

        return $user;
    }

    public function test_the_component_select_in_the_activity_modal_ignores_accents(): void
    {
        $admin = $this->admin();
        Component::factory()->create(['name' => 'Beatriz']);
        Component::factory()->create(['name' => 'Ángela']);
        Component::factory()->create(['name' => 'Ana']);

        $components = Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->viewData('components');

        $this->assertSame(['Ana', 'Ángela', 'Beatriz'], $components->pluck('name')->all());
    }

    public function test_the_teacher_select_in_assignment_form_ignores_accents(): void
    {
        $coordination = $this->coordination();
        foreach (['Beatriz', 'Ángela', 'Ana'] as $name) {
            $teacher = User::factory()->create(['name' => $name, 'is_active' => true]);
            $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());
        }

        $teachers = Livewire::actingAs($coordination)
            ->test(AssignmentForm::class)
            ->viewData('teachers');

        $this->assertSame(['Ana', 'Ángela', 'Beatriz'], $teachers->pluck('name')->all());
    }

    public function test_the_program_unit_select_in_user_form_ignores_accents(): void
    {
        $admin = $this->admin();
        ProgramUnit::factory()->create(['name' => 'Beatriz']);
        ProgramUnit::factory()->create(['name' => 'Ángela']);
        ProgramUnit::factory()->create(['name' => 'Ana']);

        $programUnits = Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->viewData('programUnits');

        $this->assertSame(['Ana', 'Ángela', 'Beatriz'], $programUnits->pluck('name')->all());
    }

    /**
     * El listado principal de Plantillas (a diferencia de los otros 5
     * catálogos) nunca usó "más reciente primero" — entra de lleno en el
     * punto 6, no es una excepción.
     */
    public function test_the_template_index_listing_ignores_accents(): void
    {
        $coordination = $this->coordination();
        DeliverableTemplate::factory()->create(['name' => 'Beatriz']);
        DeliverableTemplate::factory()->create(['name' => 'Ángela']);
        DeliverableTemplate::factory()->create(['name' => 'Ana']);

        $templates = Livewire::actingAs($coordination)
            ->test(TemplateIndex::class)
            ->viewData('templates');

        $this->assertSame(['Ana', 'Ángela', 'Beatriz'], $templates->pluck('name')->all());
    }
}
