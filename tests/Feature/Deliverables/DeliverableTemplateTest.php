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
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deliverable_templates', ['name' => 'Informe trimestral']);
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
