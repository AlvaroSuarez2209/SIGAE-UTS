<?php

namespace Tests\Feature\Leaderships;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Leaderships\LeadershipForm;
use App\Livewire\Leaderships\LeadershipIndex;
use App\Models\AcademicPeriod;
use App\Models\Leadership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo patrón que ReviewFilterRoundTripTest/DeliverableFilterRoundTripTest
 * aplicado a Líderes: "Cancelar"/"Volver" era un
 * <a href="{{ route('leaderships.index') }}"> sin parámetros, y
 * LeadershipIndex::mount() pisaba siempre $periodFilter con el periodo
 * activo (sin #[Url]) — ni "Todos los periodos" ni una búsqueda activa
 * sobrevivían al viaje de ida y vuelta.
 */
class LeadershipFilterRoundTripTest extends TestCase
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

    public function test_editing_a_leadership_and_following_cancelar_preserves_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $leader = $this->userWithRole(RoleName::Leader);
        $leadership = Leadership::factory()->create(['academic_period_id' => $period->id, 'user_id' => $leader->id]);

        $index = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(LeadershipIndex::class);
        $index->assertSet('periodFilter', null);

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*leaderships\/'.$leadership->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership]);
        $form->assertSet('periodFilter', null);

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>\s*Cancelar\s*<\/a>/');
        $this->assertArrayHasKey('periodFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(LeadershipIndex::class);
        $indexAgain->assertSet('periodFilter', null);
    }

    public function test_editing_a_leadership_and_following_cancelar_preserves_the_search_term(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $leader = User::factory()->create(['is_active' => true, 'name' => 'Laura Buscable']);
        $leader->roles()->attach(Role::where('name', RoleName::Leader->value)->first());
        $leadership = Leadership::factory()->create(['academic_period_id' => $period->id, 'user_id' => $leader->id]);

        $index = Livewire::withQueryParams(['search' => 'Laura', 'periodFilter' => (string) $period->id])
            ->actingAs($admin)
            ->test(LeadershipIndex::class);
        $index->assertSet('search', 'Laura');

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*leaderships\/'.$leadership->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership]);
        $form->assertSet('search', 'Laura');
        $form->assertSet('periodFilter', $period->id);

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>\s*Cancelar\s*<\/a>/');

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(LeadershipIndex::class);
        $indexAgain->assertSet('search', 'Laura');
        $indexAgain->assertSet('periodFilter', $period->id);
    }

    public function test_saving_a_leadership_redirects_back_preserving_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $leader = $this->userWithRole(RoleName::Leader);
        $leadership = Leadership::factory()->create(['academic_period_id' => $period->id, 'user_id' => $leader->id]);

        $form = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(LeadershipForm::class, ['leadership' => $leadership]);

        $form->set('starts_at', now()->toDateString())
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
