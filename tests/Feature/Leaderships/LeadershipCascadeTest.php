<?php

namespace Tests\Feature\Leaderships;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Leaderships\LeadershipForm;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora, punto 13: selectores en cascada Componente >
 * Subcomponente > Actividad en el formulario de liderazgo. component_id/
 * subcomponent_id son puramente de interfaz (nunca se guardan) — solo
 * acotan qué actividad ofrecer; activity_id sigue siendo el único campo
 * real persistido.
 */
class LeadershipCascadeTest extends TestCase
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

    private function leader(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Leader->value)->first());

        return $user;
    }

    public function test_choosing_a_component_offers_only_its_own_subcomponents(): void
    {
        $admin = $this->admin();
        $componentA = Component::factory()->create();
        $componentB = Component::factory()->create();
        $subcomponentA = Subcomponent::factory()->create(['component_id' => $componentA->id]);
        $subcomponentB = Subcomponent::factory()->create(['component_id' => $componentB->id]);

        $availableSubcomponents = Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('component_id', $componentA->id)
            ->viewData('availableSubcomponents');

        $this->assertTrue($availableSubcomponents->contains('id', $subcomponentA->id));
        $this->assertFalse($availableSubcomponents->contains('id', $subcomponentB->id));
    }

    public function test_choosing_a_component_offers_only_its_own_activities_and_clears_activity(): void
    {
        $admin = $this->admin();
        $componentA = Component::factory()->create();
        $componentB = Component::factory()->create();
        $activityA = Activity::factory()->create(['component_id' => $componentA->id]);
        $activityB = Activity::factory()->create(['component_id' => $componentB->id]);

        $component = Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('activity_id', $activityB->id)
            ->set('component_id', $componentA->id);

        $component->assertSet('activity_id', null);

        $activities = $component->viewData('activities');
        $this->assertTrue($activities->contains('id', $activityA->id));
        $this->assertFalse($activities->contains('id', $activityB->id));
    }

    public function test_choosing_a_subcomponent_narrows_the_activity_list_further_and_clears_activity(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id]);
        $nestedActivity = Activity::factory()->create(['component_id' => $component->id, 'subcomponent_id' => $subcomponent->id]);
        $directActivity = Activity::factory()->create(['component_id' => $component->id, 'subcomponent_id' => null]);

        $withoutSubcomponent = Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('component_id', $component->id)
            ->viewData('activities');

        $this->assertTrue($withoutSubcomponent->contains('id', $nestedActivity->id));
        $this->assertTrue($withoutSubcomponent->contains('id', $directActivity->id));

        $component2 = Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('component_id', $component->id)
            ->set('activity_id', $directActivity->id)
            ->set('subcomponent_id', $subcomponent->id);

        $component2->assertSet('activity_id', null);

        $withSubcomponent = $component2->viewData('activities');
        $this->assertTrue($withSubcomponent->contains('id', $nestedActivity->id));
        $this->assertFalse($withSubcomponent->contains('id', $directActivity->id));
    }

    public function test_no_activities_are_offered_until_a_component_is_chosen(): void
    {
        $admin = $this->admin();
        Activity::factory()->create();

        $activities = Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->viewData('activities');

        $this->assertTrue($activities->isEmpty());
    }

    /**
     * Cada nivel de la cascada oculta inactivos (punto 1) y ordena sin
     * distinguir tildes (punto 6).
     */
    public function test_each_cascade_level_hides_inactive_and_orders_without_accents(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Beatriz']);
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Ángela']);
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Inactivo', 'is_active' => false]);

        $availableSubcomponents = Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('component_id', $component->id)
            ->viewData('availableSubcomponents');

        $this->assertSame(['Ángela', 'Beatriz'], $availableSubcomponents->pluck('name')->all());
    }

    /**
     * Editar un liderazgo existente precarga la cascada completa a
     * partir de la actividad ya asignada — sin que el admin tenga que
     * volver a elegir componente/subcomponente desde cero.
     */
    public function test_editing_an_existing_leadership_preloads_the_cascade(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id]);
        $activity = Activity::factory()->create(['component_id' => $component->id, 'subcomponent_id' => $subcomponent->id]);
        $leadership = Leadership::factory()->create(['activity_id' => $activity->id]);

        Livewire::actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership])
            ->assertSet('component_id', $component->id)
            ->assertSet('subcomponent_id', $subcomponent->id)
            ->assertSet('activity_id', $activity->id);
    }

    public function test_editing_a_leadership_with_no_activity_preloads_an_empty_cascade(): void
    {
        $admin = $this->admin();
        $leadership = Leadership::factory()->create(['activity_id' => null]);

        Livewire::actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership])
            ->assertSet('component_id', null)
            ->assertSet('subcomponent_id', null)
            ->assertSet('activity_id', null);
    }

    /**
     * "Guardar produce los mismos datos que antes" — la cascada nunca
     * toca program_unit_id ni ningún otro campo real, y el liderazgo
     * sigue apuntando a la misma actividad tras editar y guardar sin
     * cambiarla.
     */
    public function test_saving_after_preloading_the_cascade_keeps_the_same_data(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id]);
        $activity = Activity::factory()->create(['component_id' => $component->id, 'subcomponent_id' => $subcomponent->id]);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $leader = $this->leader();
        $leadership = Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership])
            ->call('save')
            ->assertHasNoErrors();

        $leadership->refresh();
        $this->assertSame($activity->id, $leadership->activity_id);
        $this->assertSame($programUnit->id, $leadership->program_unit_id);
        $this->assertSame($leader->id, $leadership->user_id);
    }

    /**
     * Si el componente/subcomponente de la actividad ya asignada se
     * desactivó después, la cascada precargada al editar sigue
     * ofreciéndolos (mismo criterio del punto 1 aplicado aquí).
     */
    public function test_editing_still_offers_a_now_inactive_component_and_subcomponent(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create(['is_active' => false]);
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);
        $activity = Activity::factory()->create(['component_id' => $component->id, 'subcomponent_id' => $subcomponent->id]);
        $leadership = Leadership::factory()->create(['activity_id' => $activity->id]);

        $component2 = Livewire::actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership]);

        $this->assertTrue($component2->viewData('components')->contains('id', $component->id));
        $this->assertTrue($component2->viewData('availableSubcomponents')->contains('id', $subcomponent->id));
        $this->assertTrue($component2->viewData('activities')->contains('id', $activity->id));
    }
}
