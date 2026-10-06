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
use App\Models\DeliverableTemplate;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prioridad 4 de la revisión de la directora: bloquear la desactivación de
 * un catálogo con dependientes activos, con un mensaje específico (no
 * genérico) de cuántos y de qué tipo. Entregable y Asignación docente no
 * tienen su propia columna is_active — "activo" para ellos es pertenecer a
 * un periodo académico en Planeación o Activo (no Cerrado/Archivado), ver
 * App\Services\CatalogDependencyChecker.
 */
class CatalogDeactivationGuardTest extends TestCase
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

    // --- Componente ---

    public function test_cannot_deactivate_a_component_with_an_active_subcomponent(): void
    {
        $component = Component::factory()->create(['is_active' => true]);
        Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('toggleActive', $component)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 subcomponente activo'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 subcomponente activo'));

        $this->assertTrue($component->fresh()->is_active);
    }

    public function test_cannot_deactivate_a_component_with_an_active_activity_without_a_subcomponent(): void
    {
        $component = Component::factory()->create(['is_active' => true]);
        Activity::factory()->create(['component_id' => $component->id, 'subcomponent_id' => null, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('toggleActive', $component)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 actividad activa'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 actividad activa'));

        $this->assertTrue($component->fresh()->is_active);
    }

    public function test_can_deactivate_a_component_without_active_dependents(): void
    {
        $component = Component::factory()->create(['is_active' => true]);
        Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ComponentIndex::class)
            ->call('toggleActive', $component)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($component->fresh()->is_active);
    }

    // --- Subcomponente ---

    public function test_cannot_deactivate_a_subcomponent_with_an_active_activity(): void
    {
        $subcomponent = Subcomponent::factory()->create(['is_active' => true]);
        Activity::factory()->create([
            'component_id' => $subcomponent->component_id,
            'subcomponent_id' => $subcomponent->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SubcomponentIndex::class)
            ->call('toggleActive', $subcomponent)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 actividad activa'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 actividad activa'));

        $this->assertTrue($subcomponent->fresh()->is_active);
    }

    public function test_can_deactivate_a_subcomponent_without_active_activities(): void
    {
        $subcomponent = Subcomponent::factory()->create(['is_active' => true]);
        Activity::factory()->create([
            'component_id' => $subcomponent->component_id,
            'subcomponent_id' => $subcomponent->id,
            'is_active' => false,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SubcomponentIndex::class)
            ->call('toggleActive', $subcomponent)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($subcomponent->fresh()->is_active);
    }

    // --- Actividad ---

    public function test_cannot_deactivate_an_activity_with_a_deliverable_in_an_open_period(): void
    {
        $activity = Activity::factory()->create(['is_active' => true]);
        $openPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        Deliverable::factory()->create(['activity_id' => $activity->id, 'academic_period_id' => $openPeriod->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('toggleActive', $activity)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 entregable'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 entregable'));

        $this->assertTrue($activity->fresh()->is_active);
    }

    public function test_can_deactivate_an_activity_whose_deliverable_is_only_in_a_closed_period(): void
    {
        $activity = Activity::factory()->create(['is_active' => true]);
        $closedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        Deliverable::factory()->create(['activity_id' => $activity->id, 'academic_period_id' => $closedPeriod->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('toggleActive', $activity)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($activity->fresh()->is_active);
    }

    public function test_cannot_deactivate_an_activity_with_a_teacher_assignment_in_an_open_period(): void
    {
        $activity = Activity::factory()->create(['is_active' => true]);
        $openPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);
        TeacherAssignment::factory()->create(['activity_id' => $activity->id, 'academic_period_id' => $openPeriod->id]);

        Livewire::actingAs($this->admin())
            ->test(ActivityIndex::class)
            ->call('toggleActive', $activity)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 asignación docente'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 asignación docente'));

        $this->assertTrue($activity->fresh()->is_active);
    }

    // --- Programa ---

    public function test_cannot_deactivate_a_program_unit_with_a_teacher_assignment_in_an_open_period(): void
    {
        $programUnit = ProgramUnit::factory()->create(['is_active' => true]);
        $openPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        TeacherAssignment::factory()->create(['program_unit_id' => $programUnit->id, 'academic_period_id' => $openPeriod->id]);

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('toggleActive', $programUnit)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 asignación docente'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 asignación docente'));

        $this->assertTrue($programUnit->fresh()->is_active);
    }

    public function test_cannot_deactivate_a_program_unit_with_a_currently_active_leadership(): void
    {
        $programUnit = ProgramUnit::factory()->create(['is_active' => true]);
        Leadership::factory()->create([
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('toggleActive', $programUnit)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 liderazgo vigente'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 liderazgo vigente'));

        $this->assertTrue($programUnit->fresh()->is_active);
    }

    public function test_can_deactivate_a_program_unit_with_only_an_expired_leadership(): void
    {
        $programUnit = ProgramUnit::factory()->create(['is_active' => true]);
        Leadership::factory()->create([
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ProgramUnitIndex::class)
            ->call('toggleActive', $programUnit)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($programUnit->fresh()->is_active);
    }

    // --- Compromiso transversal ---

    public function test_cannot_deactivate_a_commitment_with_a_deliverable_in_an_open_period(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create(['is_active' => true]);
        $openPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        Deliverable::factory()->crossCutting()->create([
            'cross_cutting_commitment_id' => $commitment->id,
            'academic_period_id' => $openPeriod->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('toggleActive', $commitment)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 entregable'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 entregable'));

        $this->assertTrue($commitment->fresh()->is_active);
    }

    public function test_can_deactivate_a_commitment_whose_deliverable_is_only_in_a_closed_period(): void
    {
        $commitment = CrossCuttingCommitment::factory()->create(['is_active' => true]);
        $closedPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);
        Deliverable::factory()->crossCutting()->create([
            'cross_cutting_commitment_id' => $commitment->id,
            'academic_period_id' => $closedPeriod->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('toggleActive', $commitment)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($commitment->fresh()->is_active);
    }

    // --- Plantilla de entregables ---

    public function test_cannot_deactivate_a_template_with_a_deliverable_in_an_open_period(): void
    {
        $template = DeliverableTemplate::factory()->create(['is_active' => true]);
        $openPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        Deliverable::factory()->create([
            'deliverable_template_id' => $template->id,
            'academic_period_id' => $openPeriod->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(TemplateIndex::class)
            ->call('toggleActive', $template)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 entregable'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 entregable'));

        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_can_deactivate_a_template_without_dependents(): void
    {
        $template = DeliverableTemplate::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(TemplateIndex::class)
            ->call('toggleActive', $template)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($template->fresh()->is_active);
    }
}
