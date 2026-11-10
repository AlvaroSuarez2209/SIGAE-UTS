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
 * Punto 2 + su ajuste: borrado real (físico) de los 6 tipos de catálogo
 * que lo permiten (periodos académicos, con su regla extra de
 * protección por código, se cubren en AcademicPeriodDeletionTest). El
 * botón "Eliminar" nunca está deshabilitado — la comprobación de
 * dependencias se hace al abrir el modal, no al pintar la lista — y el
 * modal tiene dos variantes: confirmación directa (sin dependientes) o
 * bloqueo con el detalle y un botón "Desactivar" (con dependientes).
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

    public function test_the_delete_button_is_never_disabled_even_with_dependents(): void
    {
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id]);

        $html = Livewire::actingAs($this->admin())->test(ComponentIndex::class)->html();

        $this->assertStringContainsString('btn-delete', $html);
        $this->assertStringContainsString('openDeleteConfirmation('.$component->id.')', $html);
        $this->assertStringNotContainsString('cursor-not-allowed', $html);
    }

    public function test_opening_the_modal_without_dependents_shows_the_confirmation_variant(): void
    {
        $component = Component::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->assertSet('deletionBlockers', [])
            ->assertSeeHtml('Eliminar definitivamente')
            ->assertDontSeeHtml('No se puede eliminar');
    }

    public function test_opening_the_modal_with_dependents_shows_the_blocked_variant_with_quantities(): void
    {
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->assertSet('deletionBlockers', ['1 subcomponente'])
            ->assertSeeHtml('No se puede eliminar')
            ->assertSeeHtml('Tiene 1 subcomponente')
            ->assertDontSeeHtml('Eliminar definitivamente');
    }

    public function test_deactivating_from_the_blocked_modal_deactivates_and_closes_it(): void
    {
        $component = Component::factory()->create(['is_active' => true]);
        Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->call('toggleActive', $component)
            ->assertSet('deleting', null);

        $this->assertFalse($component->fresh()->is_active);
    }

    public function test_administrator_can_delete_a_component_without_dependents(): void
    {
        $component = Component::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->call('deleteComponent');

        $this->assertDatabaseMissing('components', ['id' => $component->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => Component::class,
            'auditable_id' => $component->id,
        ]);
    }

    public function test_coordination_does_not_see_the_delete_button_and_cannot_open_the_modal(): void
    {
        $component = Component::factory()->create();

        $html = Livewire::actingAs($this->coordination())->test(ComponentIndex::class)->html();
        $this->assertStringNotContainsString('openDeleteConfirmation(', $html);

        Livewire::actingAs($this->coordination())
            ->test(ComponentIndex::class)
            ->call('openDeleteConfirmation', $component)
            ->assertForbidden();

        $this->assertDatabaseHas('components', ['id' => $component->id]);
    }

    // --- Subcomponente ---

    public function test_subcomponent_modal_blocked_variant_shows_quantities_and_deactivate_works(): void
    {
        $subcomponent = Subcomponent::factory()->create(['is_active' => true]);
        Activity::factory()->create([
            'component_id' => $subcomponent->component_id,
            'subcomponent_id' => $subcomponent->id,
            'is_active' => false,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SubcomponentIndex::class)
            ->call('openDeleteConfirmation', $subcomponent)
            ->assertSet('deletionBlockers', ['1 actividad'])
            ->call('toggleActive', $subcomponent)
            ->assertSet('deleting', null);

        $this->assertFalse($subcomponent->fresh()->is_active);
    }

    public function test_administrator_can_delete_a_subcomponent_without_dependents(): void
    {
        $subcomponent = Subcomponent::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SubcomponentIndex::class)
            ->call('openDeleteConfirmation', $subcomponent)
            ->call('deleteSubcomponent');

        $this->assertDatabaseMissing('subcomponents', ['id' => $subcomponent->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => Subcomponent::class,
            'auditable_id' => $subcomponent->id,
        ]);
    }

    public function test_coordination_cannot_open_the_subcomponent_delete_modal(): void
    {
        $subcomponent = Subcomponent::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(SubcomponentIndex::class)
            ->call('openDeleteConfirmation', $subcomponent)
            ->assertForbidden();

        $this->assertDatabaseHas('subcomponents', ['id' => $subcomponent->id]);
    }

    // --- Actividad ---

    public function test_administrator_can_delete_an_activity_without_dependents(): void
    {
        $activity = Activity::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('openDeleteConfirmation', $activity)
            ->call('deleteActivity');

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => Activity::class,
            'auditable_id' => $activity->id,
        ]);
    }

    public function test_activity_modal_shows_combined_quantities_for_multiple_blockers(): void
    {
        $activity = Activity::factory()->create();
        $closedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        Deliverable::factory()->create(['activity_id' => $activity->id, 'academic_period_id' => $closedPeriod->id]);
        TeacherAssignment::factory()->create(['activity_id' => $activity->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('openDeleteConfirmation', $activity)
            ->assertSet('deletionBlockers', ['1 entregable', '1 asignación docente'])
            ->assertSeeHtml('Puedes desactivarla')
            ->assertDontSeeHtml('Eliminar definitivamente');
    }

    public function test_cannot_delete_an_activity_with_a_leadership(): void
    {
        $activity = Activity::factory()->create();
        Leadership::factory()->create(['activity_id' => $activity->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('openDeleteConfirmation', $activity)
            ->assertSet('deletionBlockers', ['1 liderazgo']);

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_coordination_cannot_open_the_activity_delete_modal(): void
    {
        $activity = Activity::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(ActivityIndex::class)
            ->call('openDeleteConfirmation', $activity)
            ->assertForbidden();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    // --- Programa ---

    public function test_administrator_can_delete_a_program_unit_without_dependents(): void
    {
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('openDeleteConfirmation', $programUnit)
            ->call('deleteProgramUnit');

        $this->assertDatabaseMissing('program_units', ['id' => $programUnit->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => ProgramUnit::class,
            'auditable_id' => $programUnit->id,
        ]);
    }

    public function test_program_unit_modal_blocks_on_a_directly_assigned_user(): void
    {
        $programUnit = ProgramUnit::factory()->create();
        User::factory()->create(['program_unit_id' => $programUnit->id]);

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('openDeleteConfirmation', $programUnit)
            ->assertSet('deletionBlockers', ['1 docente/líder asignado directamente']);

        $this->assertDatabaseHas('program_units', ['id' => $programUnit->id]);
    }

    public function test_coordination_cannot_open_the_program_unit_delete_modal(): void
    {
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(ProgramUnitIndex::class)
            ->call('openDeleteConfirmation', $programUnit)
            ->assertForbidden();

        $this->assertDatabaseHas('program_units', ['id' => $programUnit->id]);
    }

    // --- Compromiso transversal ---

    public function test_administrator_can_delete_a_commitment_without_dependents(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('openDeleteConfirmation', $commitment)
            ->call('deleteCommitment');

        $this->assertDatabaseMissing('cross_cutting_commitments', ['id' => $commitment->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog_item_deleted',
            'auditable_type' => CrossCuttingCommitment::class,
            'auditable_id' => $commitment->id,
        ]);
    }

    public function test_commitment_modal_blocks_on_a_deliverable_in_a_closed_period(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create();
        $closedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);
        Deliverable::factory()->crossCutting()->create([
            'cross_cutting_commitment_id' => $commitment->id,
            'academic_period_id' => $closedPeriod->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('openDeleteConfirmation', $commitment)
            ->assertSet('deletionBlockers', ['1 entregable']);

        $this->assertDatabaseHas('cross_cutting_commitments', ['id' => $commitment->id]);
    }

    public function test_coordination_cannot_open_the_commitment_delete_modal(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('openDeleteConfirmation', $commitment)
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
            ->call('openDeleteConfirmation', $template)
            ->call('deleteTemplate');

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
            ->call('openDeleteConfirmation', $template)
            ->call('deleteTemplate');

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
            ->call('openDeleteConfirmation', $template)
            ->call('deleteTemplate');

        $this->assertDatabaseMissing('deliverable_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('deliverable_reference_files', ['id' => $referenceFile->id]);
        Storage::disk('local')->assertExists($referenceFile->disk_path);
    }

    public function test_coordination_cannot_open_the_template_delete_modal(): void
    {
        $template = DeliverableTemplate::factory()->create();

        Livewire::actingAs($this->coordination())
            ->test(TemplateIndex::class)
            ->call('openDeleteConfirmation', $template)
            ->assertForbidden();

        $this->assertDatabaseHas('deliverable_templates', ['id' => $template->id]);
    }

    // --- Condición de carrera ---

    /**
     * Simula una dependencia creada justo entre el momento en que se
     * abrió el modal (que mostró la confirmación, sin bloqueo) y el
     * clic real: el segundo chequeo, hecho dentro de la transacción con
     * lockForUpdate(), debe atraparla igual — nunca debe llegar a un
     * 500 ni borrar el componente con una actividad ya colgada de él.
     */
    public function test_a_dependency_created_right_before_the_delete_click_is_still_caught(): void
    {
        $component = Component::factory()->create();

        Activity::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        $result = CatalogDeletionService::deleteComponent($component);

        $this->assertFalse($result->deleted);
        $this->assertStringContainsString('1 actividad', $result->message);
        $this->assertDatabaseHas('components', ['id' => $component->id]);
    }
}
