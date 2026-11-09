<?php

namespace Tests\Feature\Deliverables;

use App\Enums\RoleName;
use App\Livewire\Deliverables\DeliverableRecipients;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverableRecipientsTest extends TestCase
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
     * Punto 6 (ampliación): este listado ordena en PHP (es una colección
     * ya cargada, no una consulta) usando el nuevo macro de Collection
     * sortByAccentInsensitive(), insensible a tildes igual que
     * orderByAccentInsensitive() lo es para Builder.
     */
    public function test_recipients_list_is_ordered_alphabetically_by_name_without_accents(): void
    {
        $admin = $this->admin();
        $deliverable = Deliverable::factory()->create();

        $zoe = User::factory()->create(['name' => 'Zoe Ramírez']);
        $angela = User::factory()->create(['name' => 'Ángela Torres']);
        $beatriz = User::factory()->create(['name' => 'Beatriz Gómez']);

        Evidence::factory()->create(['deliverable_id' => $deliverable->id, 'user_id' => $zoe->id]);
        Evidence::factory()->create(['deliverable_id' => $deliverable->id, 'user_id' => $angela->id]);
        Evidence::factory()->create(['deliverable_id' => $deliverable->id, 'user_id' => $beatriz->id]);

        Livewire::actingAs($admin)
            ->test(DeliverableRecipients::class, ['deliverable' => $deliverable])
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('user.name')->all() === [
                'Ángela Torres',
                'Beatriz Gómez',
                'Zoe Ramírez',
            ]);
    }
}
