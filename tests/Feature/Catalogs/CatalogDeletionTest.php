<?php

namespace Tests\Feature\Catalogs;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Livewire\Deliverables\TemplateIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\DeliverableTemplate;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Catalog\CatalogDeletionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Punto 2: borrado real (físico) de los 6 tipos de catálogo que lo
 * permiten (periodos académicos, con su regla extra de protección por
 * código, se cubren en su propio test — ver AcademicPeriodDeletionTest).
 */
class CatalogDeletionTest extends TestCase
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

    // --- Componente ---

    public function test_administrator_can_delete_a_component_without_dependents(): void
    {
        $component = Component::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('deleteComponent', $component);

        $this->assertDatabaseMissing('components', ['id' => $component->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => Component::class,
            'auditable_id' => $component->id,
        ]);
    }

    public function test_cannot_delete_a_component_with_an_inactive_subcomponent(): void
    {
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('deleteComponent', $component)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 subcomponente'));

        $this->assertDatabaseHas('components', ['id' => $component->id]);
    }

    public function test_coordination_cannot_delete_a_component(): void
    {
        $component = Component::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(ComponentIndex::class)
            ->call('deleteComponent', $component)
            ->assertForbidden();

        $this->assertDatabaseHas('components', ['id' => $component->id]);
    }

    // --- Subcomponente ---

    public function test_administrator_can_delete_a_subcomponent_without_dependents(): void
    {
        $subcomponent = Subcomponent::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SubcomponentIndex::class)
            ->call('deleteSubcomponent', $subcomponent);

        $this->assertDatabaseMissing('subcomponents', ['id' => $subcomponent->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => Subcomponent::class,
            'auditable_id' => $subcomponent->id,
        ]);
    }

    public function test_cannot_delete_a_subcomponent_with_an_inactive_activity(): void
    {
        $subcomponent = Subcomponent::factory()->create();
        Activity::factory()->create([
            'component_id' => $subcomponent->component_id,
            'subcomponent_id' => $subcomponent->id,
            'is_active' => false,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SubcomponentIndex::class)
            ->call('deleteSubcomponent', $subcomponent)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 actividad'));

        $this->assertDatabaseHas('subcomponents', ['id' => $subcomponent->id]);
    }

    public function test_coordination_cannot_delete_a_subcomponent(): void
    {
        $subcomponent = Subcomponent::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(SubcomponentIndex::class)
            ->call('deleteSubcomponent', $subcomponent)
            ->assertForbidden();

        $this->assertDatabaseHas('subcomponents', ['id' => $subcomponent->id]);
    }

    // --- Actividad ---

    public function test_administrator_can_delete_an_activity_without_dependents(): void
    {
        $activity = Activity::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('deleteActivity', $activity);

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => Activity::class,
            'auditable_id' => $activity->id,
        ]);
    }

    public function test_cannot_delete_an_activity_with_a_deliverable_in_a_closed_period(): void
    {
        $activity = Activity::factory()->create();
        $closedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        Deliverable::factory()->create(['activity_id' => $activity->id, 'academic_period_id' => $closedPeriod->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('deleteActivity', $activity)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 entregable'));

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_cannot_delete_an_activity_with_a_teacher_assignment(): void
    {
        $activity = Activity::factory()->create();
        TeacherAssignment::factory()->create(['activity_id' => $activity->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('deleteActivity', $activity)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 asignación docente'));

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_cannot_delete_an_activity_with_a_leadership(): void
    {
        $activity = Activity::factory()->create();
        Leadership::factory()->create(['activity_id' => $activity->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('deleteActivity', $activity)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 liderazgo'));

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_coordination_cannot_delete_an_activity(): void
    {
        $activity = Activity::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(ActivityIndex::class)
            ->call('deleteActivity', $activity)
            ->assertForbidden();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    // --- Programa ---

    public function test_administrator_can_delete_a_program_unit_without_dependents(): void
    {
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('deleteProgramUnit', $programUnit);

        $this->assertDatabaseMissing('program_units', ['id' => $programUnit->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => ProgramUnit::class,
            'auditable_id' => $programUnit->id,
        ]);
    }

    public function test_cannot_delete_a_program_unit_with_an_expired_leadership(): void
    {
        $programUnit = ProgramUnit::factory()->create();
        Leadership::factory()->create([
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('deleteProgramUnit', $programUnit)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 liderazgo'));

        $this->assertDatabaseHas('program_units', ['id' => $programUnit->id]);
    }

    public function test_cannot_delete_a_program_unit_with_a_user_assigned_directly(): void
    {
        $programUnit = ProgramUnit::factory()->create();
        User::factory()->create(['program_unit_id' => $programUnit->id]);

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('deleteProgramUnit', $programUnit)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], 'asignado directamente'));

        $this->assertDatabaseHas('program_units', ['id' => $programUnit->id]);
    }

    public function test_coordination_cannot_delete_a_program_unit(): void
    {
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(ProgramUnitIndex::class)
            ->call('deleteProgramUnit', $programUnit)
            ->assertForbidden();

        $this->assertDatabaseHas('program_units', ['id' => $programUnit->id]);
    }

    // --- Compromiso transversal ---

    public function test_administrator_can_delete_a_commitment_without_dependents(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('deleteCommitment', $commitment);

        $this->assertDatabaseMissing('cross_cutting_commitments', ['id' => $commitment->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => CrossCuttingCommitment::class,
            'auditable_id' => $commitment->id,
        ]);
    }

    public function test_cannot_delete_a_commitment_with_a_deliverable_in_a_closed_period(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create();
        $closedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);
        Deliverable::factory()->crossCutting()->create([
            'cross_cutting_commitment_id' => $commitment->id,
            'academic_period_id' => $closedPeriod->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('deleteCommitment', $commitment)
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 entregable'));

        $this->assertDatabaseHas('cross_cutting_commitments', ['id' => $commitment->id]);
    }

    public function test_coordination_cannot_delete_a_commitment(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('deleteCommitment', $commitment)
            ->assertForbidden();

        $this->assertDatabaseHas('cross_cutting_commitments', ['id' => $commitment->id]);
    }

    // --- Plantilla de entregables ---

    public function test_administrator_can_delete_a_template_even_after_it_was_used_to_create_a_deliverable(): void
    {
        $template = DeliverableTemplate::factory()->create();
        $deliverable = Deliverable::factory()->create(['deliverable_template_id' => $template->id]);

        Livewire::actingAs($this->admin())
            ->test(TemplateIndex::class)
            ->call('deleteTemplate', $template);

        $this->assertDatabaseMissing('deliverable_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('deliverables', ['id' => $deliverable->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => DeliverableTemplate::class,
            'auditable_id' => $template->id,
        ]);
    }

    public function test_deleting_a_template_deletes_its_reference_file_rows_and_the_physical_file_when_not_shared(): void
    {
        Storage::fake('local');

        $template = DeliverableTemplate::factory()->create();
        $file = UploadedFile::fake()->create('instructivo.pdf', 10);
        $referenceFile = $template->attachReferenceFile($file, $this->admin());

        Storage::disk('local')->assertExists($referenceFile->disk_path);

        Livewire::actingAs($this->admin())
            ->test(TemplateIndex::class)
            ->call('deleteTemplate', $template);

        $this->assertDatabaseMissing('deliverable_reference_files', ['id' => $referenceFile->id]);
        Storage::disk('local')->assertMissing($referenceFile->disk_path);
    }

    public function test_deleting_a_template_does_not_delete_the_physical_file_when_the_disk_path_is_shared(): void
    {
        Storage::fake('local');

        $template = DeliverableTemplate::factory()->create();
        $file = UploadedFile::fake()->create('instructivo.pdf', 10);
        $referenceFile = $template->attachReferenceFile($file, $this->admin());

        // Simula la copia que hace DeliverableForm::save() al crear un
        // entregable a partir de la plantilla: otra fila comparte el
        // mismo disk_path.
        DeliverableReferenceFile::create([
            'deliverable_id' => Deliverable::factory()->create()->id,
            'original_name' => $referenceFile->original_name,
            'stored_name' => 'copy-'.$referenceFile->stored_name,
            'disk_path' => $referenceFile->disk_path,
            'mime_type' => $referenceFile->mime_type,
            'size_bytes' => $referenceFile->size_bytes,
            'uploaded_by' => $this->admin()->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(TemplateIndex::class)
            ->call('deleteTemplate', $template);

        $this->assertDatabaseMissing('deliverable_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('deliverable_reference_files', ['id' => $referenceFile->id]);
        Storage::disk('local')->assertExists($referenceFile->disk_path);
    }

    public function test_coordination_cannot_delete_a_template(): void
    {
        $template = DeliverableTemplate::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(TemplateIndex::class)
            ->call('deleteTemplate', $template)
            ->assertForbidden();

        $this->assertDatabaseHas('deliverable_templates', ['id' => $template->id]);
    }

    // --- Condición de carrera ---

    /**
     * Simula una dependencia creada justo entre el render (que decidió
     * mostrar el botón "Eliminar" habilitado) y el clic real: el segundo
     * chequeo, hecho dentro de la transacción con lockForUpdate(), debe
     * atraparla igual — nunca debe llegar a un 500 ni borrar el
     * componente con una actividad ya colgada de él.
     */
    public function test_a_dependency_created_right_before_the_delete_click_is_still_caught(): void
    {
        $component = Component::factory()->create();

        // La actividad aparece después del momento en que la UI
        // decidió que el botón debía estar habilitado.
        Activity::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        $result = CatalogDeletionService::deleteComponent($component);

        $this->assertFalse($result->deleted);
        $this->assertStringContainsString('1 actividad', $result->message);
        $this->assertDatabaseHas('components', ['id' => $component->id]);
    }
}
