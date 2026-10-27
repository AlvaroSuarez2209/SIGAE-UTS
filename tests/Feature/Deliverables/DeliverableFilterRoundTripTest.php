<?php

namespace Tests\Feature\Deliverables;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Deliverables\DeliverableForm;
use App\Livewire\Deliverables\DeliverableIndex;
use App\Livewire\Deliverables\DeliverableRecipients;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo patrón que ReviewFilterRoundTripTest (commit 3be7623), aplicado
 * a Entregables: "Volver"/"Cancelar"/"Volver a Entregables" eran
 * <a href="{{ route('deliverables.index') }}"> sin parámetros, y
 * DeliverableIndex::mount() pisaba siempre $periodFilter con el
 * periodo activo (sin #[Url] en absoluto) — "Todos los periodos" nunca
 * sobrevivía ni a una recarga simple, mucho menos a un "Volver".
 */
class DeliverableFilterRoundTripTest extends TestCase
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

    public function test_editing_a_deliverable_and_following_cancelar_preserves_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);

        $index = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(DeliverableIndex::class);
        $index->assertSet('periodFilter', null);

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*deliverables\/'.$deliverable->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable]);
        $form->assertSet('periodFilter', null);

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>Cancelar<\/a>/');
        $this->assertArrayHasKey('periodFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(DeliverableIndex::class);
        $indexAgain->assertSet('periodFilter', null);
    }

    public function test_editing_a_deliverable_and_following_cancelar_preserves_a_specific_period_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);

        $index = Livewire::withQueryParams(['periodFilter' => (string) $period->id])
            ->actingAs($admin)
            ->test(DeliverableIndex::class);
        $index->assertSet('periodFilter', $period->id);

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*deliverables\/'.$deliverable->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable]);
        $form->assertSet('periodFilter', $period->id);

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>Cancelar<\/a>/');

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(DeliverableIndex::class);
        $indexAgain->assertSet('periodFilter', $period->id);
    }

    public function test_viewing_recipients_and_following_volver_preserves_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);

        $index = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(DeliverableIndex::class);

        $toRecipientsQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*deliverables\/'.$deliverable->id.'\/recipients[^"]*)"[^>]*>/');

        $recipients = Livewire::withQueryParams($toRecipientsQuery)
            ->actingAs($admin)
            ->test(DeliverableRecipients::class, ['deliverable' => $deliverable]);
        $recipients->assertSet('periodFilter', null);

        $backToIndexQuery = $this->queryParamsFromHref($recipients->html(), '/<a href="([^"]*)"[^>]*>Volver a Entregables<\/a>/');
        $this->assertArrayHasKey('periodFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(DeliverableIndex::class);
        $indexAgain->assertSet('periodFilter', null);
    }

    public function test_saving_as_draft_redirects_back_preserving_the_all_periods_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->draft()->create(['academic_period_id' => $period->id]);

        $form = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable]);

        $form->call('saveAsDraft')->assertHasNoErrors();

        $redirectUrl = $form->effects['redirect'] ?? null;
        $this->assertNotNull($redirectUrl);

        $query = [];
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('periodFilter', $query);
        $this->assertSame('', $query['periodFilter']);
    }
}
