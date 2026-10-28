<?php

namespace Tests\Feature\Deliverables;

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

/**
 * Mismo patrón que las pantallas anteriores aplicado a Plantillas de
 * entregables: "Cancelar" era un
 * <a href="{{ route('deliverable-templates.index') }}"> sin
 * parámetros, y TemplateIndex::$search/$statusFilter nunca tuvieron
 * #[Url].
 */
class TemplateFilterRoundTripTest extends TestCase
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

    public function test_editing_a_template_and_following_cancelar_preserves_the_search_and_status_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create(['name' => 'Plantilla Buscable', 'is_active' => true]);

        $index = Livewire::withQueryParams(['search' => 'Buscable', 'statusFilter' => 'active'])
            ->actingAs($admin)
            ->test(TemplateIndex::class);
        $index->assertSet('search', 'Buscable');
        $index->assertSet('statusFilter', 'active');

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*deliverable-templates\/'.$template->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template]);
        $form->assertSet('search', 'Buscable');
        $form->assertSet('statusFilter', 'active');

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>Cancelar<\/a>/');
        $this->assertArrayHasKey('search', $backToIndexQuery);
        $this->assertArrayHasKey('statusFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(TemplateIndex::class);
        $indexAgain->assertSet('search', 'Buscable');
        $indexAgain->assertSet('statusFilter', 'active');
    }

    public function test_saving_a_template_redirects_back_preserving_the_search_and_status_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create();

        $form = Livewire::withQueryParams(['search' => 'algo', 'statusFilter' => 'active'])
            ->actingAs($admin)
            ->test(TemplateForm::class, ['deliverableTemplate' => $template]);

        $form->call('save')->assertHasNoErrors();

        $redirectUrl = $form->effects['redirect'] ?? null;
        $this->assertNotNull($redirectUrl);

        $query = [];
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertSame('algo', $query['search'] ?? null);
        $this->assertSame('active', $query['statusFilter'] ?? null);
    }
}
