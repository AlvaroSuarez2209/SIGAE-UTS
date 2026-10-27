<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo patrón que las pantallas anteriores aplicado a Usuarios:
 * "Cancelar" era un <a href="{{ route('admin.users.index') }}"> sin
 * parámetros, y UserIndex::$search/$roleFilter nunca tuvieron #[Url] —
 * ni la búsqueda ni el filtro de rol sobrevivían al viaje de ida y
 * vuelta.
 */
class UserFilterRoundTripTest extends TestCase
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

    public function test_editing_a_user_and_following_cancelar_preserves_the_search_and_role_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = User::factory()->create(['is_active' => true, 'name' => 'Teodora Buscable']);
        $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        $index = Livewire::withQueryParams(['search' => 'Teodora', 'roleFilter' => 'teacher'])
            ->actingAs($admin)
            ->test(UserIndex::class);
        $index->assertSet('search', 'Teodora');
        $index->assertSet('roleFilter', 'teacher');

        $toFormQuery = $this->queryParamsFromHref($index->html(), '/<a href="([^"]*admin\/users\/'.$teacher->id.'\/edit[^"]*)"[^>]*>Editar<\/a>/');

        $form = Livewire::withQueryParams($toFormQuery)
            ->actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher]);
        $form->assertSet('search', 'Teodora');
        $form->assertSet('roleFilter', 'teacher');

        $backToIndexQuery = $this->queryParamsFromHref($form->html(), '/<a href="([^"]*)"[^>]*>Cancelar<\/a>/');
        $this->assertArrayHasKey('search', $backToIndexQuery);
        $this->assertArrayHasKey('roleFilter', $backToIndexQuery);

        $indexAgain = Livewire::withQueryParams($backToIndexQuery)
            ->actingAs($admin)
            ->test(UserIndex::class);
        $indexAgain->assertSet('search', 'Teodora');
        $indexAgain->assertSet('roleFilter', 'teacher');
    }

    public function test_saving_a_user_redirects_back_preserving_the_search_and_role_filter(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = \App\Models\ProgramUnit::factory()->create();
        $teacher = User::factory()->create([
            'is_active' => true,
            'document_type' => 'CC',
            'program_unit_id' => $programUnit->id,
        ]);
        $teacher->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        $form = Livewire::withQueryParams(['search' => 'algo', 'roleFilter' => 'teacher'])
            ->actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher]);

        $form->call('save')->assertHasNoErrors();

        $redirectUrl = $form->effects['redirect'] ?? null;
        $this->assertNotNull($redirectUrl);

        $query = [];
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertSame('algo', $query['search'] ?? null);
        $this->assertSame('teacher', $query['roleFilter'] ?? null);
    }
}
