<?php

namespace Tests\Feature\Deliverables;

use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Deliverables\TemplateForm;
use App\Livewire\Deliverables\TemplateIndex;
use App\Models\DeliverableTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverableTemplateTest extends TestCase
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

    public function test_coordination_can_create_a_template(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(TemplateForm::class)
            ->set('name', 'Informe trimestral')
            ->set('periodicity_type', 'by_term')
            ->set('allowed_evidence_types', [EvidenceType::File->value])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('deliverable-templates.index'));

        $this->assertDatabaseHas('deliverable_templates', ['name' => 'Informe trimestral']);

        // Bloque de ajustes de interfaz, punto 5: este flash se perdía —
        // redirigía a deliverable-templates.index, que no tenía ningún
        // bloque que lo mostrara. Confirma con una petición real (no
        // Livewire::test(), que no renderiza el layout) que ahora sí llega.
        $this->actingAs($coordination)->get(route('deliverable-templates.index'))->assertSee('Plantilla guardada correctamente.');
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: el botón "Guardar" no tenía
     * ningún estado de carga — mismo patrón ya usado en
     * Login/InstitutionSettingsForm/TeacherImportWizard.
     */
    public function test_the_save_button_disables_itself_and_shows_a_loading_state(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(TemplateForm::class)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="save"', false)
            ->assertSee('Guardando...');
    }

    /**
     * Prioridad 4: antes de este cambio, TemplateForm no validaba nombres
     * duplicados en absoluto (ni en BD ni en formulario) — a diferencia de
     * los 5 catálogos bajo "Catálogos", que ya lo tenían resuelto. Mismo
     * criterio que los demás: insensible a mayúsculas/tildes, alcance
     * global (no hay un "padre" del que dependa una plantilla).
     */
    public function test_template_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        DeliverableTemplate::factory()->create(['name' => 'Informe Trimestral']);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class)
            ->set('name', 'INFORME TRIMESTRAL')
            ->set('periodicity_type', 'single')
            ->set('allowed_evidence_types', [EvidenceType::File->value])
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseCount('deliverable_templates', 1);
    }

    public function test_editing_a_template_does_not_flag_its_own_unchanged_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create(['name' => 'Informe Trimestral']);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template])
            ->set('is_mandatory', false)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_template_requires_at_least_one_evidence_type(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class)
            ->set('name', 'Sin tipos de evidencia')
            ->set('periodicity_type', 'single')
            ->set('allowed_evidence_types', [])
            ->call('save')
            ->assertHasErrors('allowed_evidence_types');

        $this->assertDatabaseCount('deliverable_templates', 0);
    }

    public function test_deactivating_a_template_preserves_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(TemplateIndex::class)
            ->call('toggleActive', $template);

        $this->assertDatabaseHas('deliverable_templates', ['id' => $template->id, 'is_active' => false]);
    }

    public function test_editing_a_template_prefills_its_plain_array_fields_without_crashing(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create([
            'allowed_evidence_types' => [EvidenceType::File->value, EvidenceType::Link->value],
            'allowed_file_types' => ['pdf', 'zip'],
        ]);

        Livewire::actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template])
            ->assertSet('allowed_evidence_types', [EvidenceType::File->value, EvidenceType::Link->value])
            ->assertSet('allowed_file_types', ['pdf', 'zip']);
    }
}
