<?php

namespace Tests\Feature\Deliverables;

use App\Enums\RoleName;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Models\CrossCuttingCommitment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrossCuttingCommitmentTest extends TestCase
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

    public function test_coordination_can_create_a_cross_cutting_commitment(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('openCreate')
            ->set('name', 'Gestión documental interna')
            ->call('save');

        $this->assertDatabaseHas('cross_cutting_commitments', ['name' => 'Gestión documental interna', 'is_active' => true]);
    }

    public function test_teacher_cannot_manage_cross_cutting_commitments(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/catalogs/cross-cutting-commitments')->assertForbidden();
    }

    public function test_deactivating_a_commitment_does_not_delete_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $commitment = CrossCuttingCommitment::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('toggleActive', $commitment);

        $this->assertDatabaseHas('cross_cutting_commitments', ['id' => $commitment->id, 'is_active' => false]);
    }
}
