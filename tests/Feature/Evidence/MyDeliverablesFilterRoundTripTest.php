<?php

namespace Tests\Feature\Evidence;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Livewire\Evidence\MyDeliverables;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo patrón que las pantallas anteriores aplicado a "Mis
 * entregables": "Volver" era un
 * <a href="{{ route('my-deliverables.index') }}"> sin parámetros, y
 * MyDeliverables::$periodFilter nunca tuvo #[Url] (a diferencia de
 * $statusFilter, que sí lo tenía) — mount() pisaba siempre su valor con
 * el periodo activo.
 */
class MyDeliverablesFilterRoundTripTest extends TestCase
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

    public function test_opening_an_evidence_and_following_volver_preserves_the_all_periods_filter(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);
        $deliverable->recipients()->attach($teacher->id);
        $evidence = Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id]);

        $index = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($teacher)
            ->test(MyDeliverables::class);
        $index->assertSet('periodFilter', null);

        $toWorkspaceQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*my-deliverables\/'.$evidence->id.'[^"]*)"[^>]*>/');

        $workspace = Livewire::withQueryParams($toWorkspaceQuery)
            ->actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence]);
        $workspace->assertSet('periodFilter', null);

        $backToIndexQuery = $this->queryParamsFromHref($workspace->html(), '/<a href="([^"]*)"[^>]*>Volver<\/a>/');
        $this->assertArrayHasKey('periodFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($teacher)
            ->test(MyDeliverables::class);
        $indexAgain->assertSet('periodFilter', null);
    }

    public function test_opening_an_evidence_and_following_volver_preserves_the_status_filter(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);
        $deliverable->recipients()->attach($teacher->id);
        $evidence = Evidence::factory()->create(['user_id' => $teacher->id, 'deliverable_id' => $deliverable->id]);

        $index = Livewire::withQueryParams(['status' => 'pending'])
            ->actingAs($teacher)
            ->test(MyDeliverables::class);
        $index->assertSet('statusFilter', 'pending');

        $toWorkspaceQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*my-deliverables\/'.$evidence->id.'[^"]*)"[^>]*>/');

        $workspace = Livewire::withQueryParams($toWorkspaceQuery)
            ->actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence]);
        $workspace->assertSet('statusFilter', 'pending');

        $backToIndexQuery = $this->queryParamsFromHref($workspace->html(), '/<a href="([^"]*)"[^>]*>Volver<\/a>/');

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($teacher)
            ->test(MyDeliverables::class);
        $indexAgain->assertSet('statusFilter', 'pending');
    }
}
