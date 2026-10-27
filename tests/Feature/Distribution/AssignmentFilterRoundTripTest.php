<?php

namespace Tests\Feature\Distribution;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Distribution\AssignmentForm;
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
 * Mismo patrón que las pantallas anteriores aplicado a Distribución:
 * "Cancelar"/"Volver" era un <a href="{{ route('distribution.index') }}">
 * sin parámetros, y AssignmentIndex::mount() pisaba siempre
 * $periodFilter con el periodo activo (sin #[Url]).
 */
class AssignmentFilterRoundTripTest extends TestCase
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

    /**
     * @return array<string, string>
     */
    private function queryParamsFromHref(string $html, string $linkPattern): array
    {
        $this->assertMatchesRegularExpression($linkPattern, $html, 'No se encontró el enlace esperado en el HTML renderizado.');

        preg_match($linkPattern, $html, $matches);
        $href = html_entity_decode($matches[1]);

        $query = [];
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_editing_an_assignment_and_following_cancelar_preserves_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'user_id' => $teacher->id,
        ]);

        $index = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(AssignmentIndex::class);
        $index->assertSet('periodFilter', null);

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*distribution\/'.$assignment->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment]);
        $form->assertSet('periodFilter', null);

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>\s*Cancelar\s*<\/a>/');
        $this->assertArrayHasKey('periodFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(AssignmentIndex::class);
        $indexAgain->assertSet('periodFilter', null);
    }

    public function test_editing_an_assignment_and_following_cancelar_preserves_the_search_term(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $teacher = User::factory()->create(['is_active' => true, 'name' => 'Teodora Buscable']);
        $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'user_id' => $teacher->id,
        ]);

        $index = Livewire::withQueryParams(['search' => 'Teodora', 'periodFilter' => (string) $period->id])
            ->actingAs($admin)
            ->test(AssignmentIndex::class);
        $index->assertSet('search', 'Teodora');

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*distribution\/'.$assignment->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment]);
        $form->assertSet('search', 'Teodora');
        $form->assertSet('periodFilter', $period->id);

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>\s*Cancelar\s*<\/a>/');

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(AssignmentIndex::class);
        $indexAgain->assertSet('search', 'Teodora');
        $indexAgain->assertSet('periodFilter', $period->id);
    }

    public function test_saving_an_assignment_redirects_back_preserving_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'user_id' => $teacher->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        $form = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment]);

        $form->set('assigned_hours', '5')
            ->call('save')
            ->assertHasNoErrors();

        $redirectUrl = $form->effects['redirect'] ?? null;
        $this->assertNotNull($redirectUrl);

        $query = [];
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('periodFilter', $query);
        $this->assertSame('', $query['periodFilter']);
    }
}
