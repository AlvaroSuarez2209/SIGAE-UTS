<?php

namespace Tests\Feature\Deliverables;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Deliverables\DeliverableIndex;
use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverableIndexTest extends TestCase
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

    /**
     * Un borrador es lo que más necesita atención (hay que decidir si se
     * publica o se descarta), así que nunca debe quedar enterrado entre
     * los publicados. Orden esperado: borradores primero, publicados
     * después — dentro de cada grupo, más reciente primero por fecha de
     * creación del registro (corrección post-prueba: antes los
     * publicados se ordenaban entre sí por fecha límite ascendente).
     */
    public function test_drafts_appear_first_then_published_both_newest_created_first(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $draftOlder = Deliverable::factory()->draft()->create([
            'academic_period_id' => $period->id,
            'name' => 'Borrador antiguo',
            'created_at' => now()->subDays(3),
            'due_at' => now()->addDays(30),
        ]);
        $draftNewer = Deliverable::factory()->draft()->create([
            'academic_period_id' => $period->id,
            'name' => 'Borrador reciente',
            'created_at' => now()->subDay(),
            'due_at' => now()->addDays(5),
        ]);
        // due_at al revés de created_at a propósito: si el orden
        // resultante respetara due_at en vez de created_at, este par
        // saldría en el orden contrario al esperado abajo.
        $publishedOlder = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'name' => 'Publicado antiguo',
            'created_at' => now()->subDays(10),
            'due_at' => now()->addDays(2),
        ]);
        $publishedNewer = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'name' => 'Publicado reciente',
            'created_at' => now()->subDay(),
            'due_at' => now()->addDays(20),
        ]);

        // mount() ya selecciona automáticamente el único periodo Activo
        // que existe en este test — no hace falta forzar periodFilter.
        $deliverables = Livewire::actingAs($this->admin())
            ->test(DeliverableIndex::class)
            ->viewData('deliverables');

        $this->assertEquals(
            [$draftNewer->id, $draftOlder->id, $publishedNewer->id, $publishedOlder->id],
            $deliverables->pluck('id')->all()
        );
    }
}
