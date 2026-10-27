<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Reviews\ReviewInbox;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La directora encontró la causa real del filtro de periodo que se
 * pierde: no es al navegar con el botón «atrás» del navegador (eso ya
 * funciona, #[Url] en ReviewInbox se encarga), sino al pulsar el enlace
 * «Volver» de review-show.blade.php — un <a href="{{ route('reviews.index') }}">
 * sin ningún parámetro, que siempre vuelve a la bandeja como si no
 * hubiera ningún filtro, perdiendo "Todos los periodos" (y cualquier
 * otro valor) sin importar qué URL tuviera la bandeja al salir de ella.
 *
 * Esta prueba sigue el recorrido real tal como lo renderiza la propia
 * vista (extrae el href de "Revisar" y de "Volver" del HTML, no
 * construye la URL a mano) para que de verdad reproduzca lo que un clic
 * real haría.
 */
class ReviewFilterRoundTripTest extends TestCase
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

    private function submittedEvidenceWithLeader(): array
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        \App\Models\Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'activity_id' => null,
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        return [$evidence->fresh(), $leader];
    }

    /**
     * @return array<string, string>
     */
    private function queryParamsFromHref(string $html, string $linkText): array
    {
        $pattern = '/<a href="([^"]*)"[^>]*>\s*'.preg_quote($linkText, '/').'\s*<\/a>/';
        $this->assertMatchesRegularExpression($pattern, $html, "No se encontró un enlace \"{$linkText}\" en el HTML renderizado.");

        preg_match($pattern, $html, $matches);
        $href = html_entity_decode($matches[1]);

        $query = [];
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_following_the_rendered_volver_link_preserves_the_all_periods_filter(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        // 1. Abre la bandeja con "Todos los periodos" explícito en la URL
        // (el caso real que reportó la directora — no la ausencia del
        // parámetro, sino su presencia vacía).
        $inbox = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($leader)
            ->test(ReviewInbox::class);
        $inbox->assertSet('periodFilter', null);

        // 2. Sigue el enlace "Revisar" exactamente como lo renderiza la vista.
        $toShowQuery = $this->queryParamsFromHref($inbox->html(), 'Revisar');

        $show = Livewire::withQueryParams($toShowQuery)
            ->actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence]);
        $show->assertSet('periodFilter', null);

        // 3. Sigue el enlace "Volver" exactamente como lo renderiza la vista.
        $backToInboxQuery = $this->queryParamsFromHref($show->html(), 'Volver');

        // Antes del fix, este enlace era route('reviews.index') sin
        // ningún parámetro — $backToInboxQuery habría quedado vacío, y
        // el siguiente mount() habría vuelto a aplicar el periodo activo
        // en vez de conservar "Todos los periodos".
        $this->assertArrayHasKey('periodFilter', $backToInboxQuery);

        $inboxAgain = Livewire::withQueryParams($backToInboxQuery)
            ->actingAs($leader)
            ->test(ReviewInbox::class);

        $inboxAgain->assertSet('periodFilter', null);
    }

    public function test_following_the_rendered_volver_link_preserves_a_specific_period_filter(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $ownPeriodId = $evidence->deliverable->academic_period_id;

        $inbox = Livewire::withQueryParams(['periodFilter' => (string) $ownPeriodId])
            ->actingAs($leader)
            ->test(ReviewInbox::class);
        $inbox->assertSet('periodFilter', $ownPeriodId);

        $toShowQuery = $this->queryParamsFromHref($inbox->html(), 'Revisar');

        $show = Livewire::withQueryParams($toShowQuery)
            ->actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence]);
        $show->assertSet('periodFilter', $ownPeriodId);

        $backToInboxQuery = $this->queryParamsFromHref($show->html(), 'Volver');

        $inboxAgain = Livewire::withQueryParams($backToInboxQuery)
            ->actingAs($leader)
            ->test(ReviewInbox::class);

        $inboxAgain->assertSet('periodFilter', $ownPeriodId);
    }

    /**
     * Las acciones reales (aprobar/devolver) redirigen a la misma
     * bandeja — deben conservar el filtro igual que "Volver", no solo el
     * enlace de salida sin acción.
     */
    public function test_approving_redirects_back_to_the_inbox_preserving_the_all_periods_filter(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        $show = Livewire::withQueryParams(['periodFilter' => ''])
            ->actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence]);

        $show->call('approve')->assertHasNoErrors();

        $redirectUrl = $show->effects['redirect'] ?? null;
        $this->assertNotNull($redirectUrl);

        $query = [];
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('periodFilter', $query);
        $this->assertSame('', $query['periodFilter']);
    }
}
