<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $attributes));
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    public function test_administrator_can_view_user_list(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_teacher_cannot_view_user_list(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_leader_cannot_view_user_list(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($leader)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_administrator_can_create_a_user_with_roles(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'nuevo.docente@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', $programUnit->id)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        // Bloque de ajustes de interfaz, punto 5: este flash se perdía —
        // redirigía a admin.users.index, que no tenía ningún bloque que lo
        // mostrara. Confirma con una petición real (no Livewire::test(),
        // que no renderiza el layout) que ahora sí llega.
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('Usuario guardado correctamente.');

        $this->assertDatabaseHas('users', ['email' => 'nuevo.docente@sigae.local']);

        $created = User::where('email', 'nuevo.docente@sigae.local')->first();
        $this->assertTrue($created->hasRole(RoleName::Teacher));
    }

    /**
     * Revisión del diagnóstico de document_type/program_unit_id: antes
     * solo los llenaba la importación masiva — ahora UserForm también
     * puede crearlos y editarlos, persistiendo ambos.
     */
    public function test_document_type_and_program_unit_are_saved_on_create_and_on_edit(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'nuevo.docente.datos@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', $programUnit->id)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'nuevo.docente.datos@sigae.local')->first();
        $this->assertEquals('CC', $created->document_type);
        $this->assertEquals($programUnit->id, $created->program_unit_id);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $created])
            ->set('document_type', 'CE')
            ->set('program_unit_id', $otherProgramUnit->id)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $created->refresh();
        $this->assertEquals('CE', $created->document_type);
        $this->assertEquals($otherProgramUnit->id, $created->program_unit_id);
    }

    public function test_document_type_and_program_unit_are_required_for_teacher_or_leader(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Sin Datos De Programa')
            ->set('email', 'sindatos@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Leader->value])
            ->call('save')
            ->assertHasErrors(['document_type', 'program_unit_id']);

        $this->assertDatabaseMissing('users', ['email' => 'sindatos@sigae.local']);
    }

    /**
     * A diferencia de Docente/Líder, un rol exclusivamente de gestión
     * (Administrador, Coordinación, Auditor) no necesita tipo de
     * documento ni programa de adscripción — "programa de adscripción"
     * no tiene sentido real para esos roles (ver diagnóstico).
     */
    public function test_document_type_and_program_unit_are_optional_for_management_only_roles(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Coordinadora Sin Programa')
            ->set('email', 'coordinadora.sinprograma@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'coordinadora.sinprograma@sigae.local')->first();
        $this->assertNull($created->document_type);
        $this->assertNull($created->program_unit_id);
    }

    public function test_a_nonexistent_program_unit_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'programainexistente@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->set('document_type', 'CC')
            ->set('program_unit_id', 999999)
            ->call('save')
            ->assertHasErrors('program_unit_id');

        $this->assertDatabaseMissing('users', ['email' => 'programainexistente@sigae.local']);
    }

    /**
     * Aviso informativo (nunca bloqueante): solo debe aparecer cuando
     * editar a esta persona sí podría malinterpretarse como un cambio de
     * distribución real — es decir, cuando ya tiene asignaciones o
     * liderazgos vigentes en el periodo activo. Nunca al crear una cuenta
     * nueva, ni al editar a alguien sin ninguno de los dos.
     */
    public function test_program_change_notice_appears_only_with_vigente_assignments_or_leaderships(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

        $teacherWithAssignment = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => $programUnit->id,
        ]);
        TeacherAssignment::factory()->create([
            'user_id' => $teacherWithAssignment->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        $teacherWithoutAssignment = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => $programUnit->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacherWithAssignment])
            ->assertSee('no modifica su distribución ni sus liderazgos actuales');

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacherWithoutAssignment])
            ->assertDontSee('no modifica su distribución ni sus liderazgos actuales');

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->assertDontSee('no modifica su distribución ni sus liderazgos actuales');
    }

    /**
     * Cambio de presentación (reordenar los campos): el "(opcional)" de
     * Tipo de documento y Programa debe seguir reaccionando en vivo al
     * marcar/desmarcar Docente o Líder — reordenar los campos no debe
     * tocar el `wire:model.live` de los checkboxes de rol.
     */
    public function test_the_optional_hint_reacts_live_to_toggling_teacher_or_leader(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $component = Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->assertSee('(opcional)');

        $component->set('selectedRoles', [RoleName::Coordination->value, RoleName::Teacher->value])
            ->assertDontSee('(opcional)');

        $component->set('selectedRoles', [RoleName::Coordination->value])
            ->assertSee('(opcional)');
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: el botón "Guardar" no tenía
     * ningún estado de carga — mismo patrón ya usado en
     * Login/InstitutionSettingsForm/TeacherImportWizard.
     */
    public function test_the_save_button_disables_itself_and_shows_a_loading_state(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="save"', false)
            ->assertSee('Guardando...');
    }

    public function test_creating_a_user_requires_at_least_one_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Sin Rol')
            ->set('email', 'sinrol@sigae.local')
            ->set('password', 'Password123!')
            ->set('selectedRoles', [])
            ->call('save')
            ->assertHasErrors('selectedRoles');
    }

    public function test_creating_a_user_with_a_weak_password_fails_validation(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'otro.docente@sigae.local')
            ->set('password', 'password123')
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->call('save')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'otro.docente@sigae.local']);
    }

    public function test_password_is_optional_when_editing_an_existing_user(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $existing = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);
        $originalPassword = $existing->password;

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $existing])
            ->set('name', 'Nombre Actualizado')
            ->set('password', '')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $existing->refresh();
        $this->assertEquals('Nombre Actualizado', $existing->name);
        $this->assertEquals($originalPassword, $existing->password);
    }

    public function test_administrator_can_toggle_another_users_active_status(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $teacher);

        $this->assertFalse($teacher->fresh()->is_active);
    }

    public function test_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $admin)
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_removing_teacher_role_is_blocked_when_user_has_pending_evidences(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Teacher));
    }

    public function test_removing_teacher_role_is_allowed_when_no_pending_evidences(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($teacher->fresh()->hasRole(RoleName::Teacher));
    }

    /**
     * Réplica del alcance exacto que usa User::canLeadAssignment() —
     * período + programa + actividad, dentro de starts_at/ends_at — para
     * dejar una revisión "Submitted" realmente pendiente bajo el
     * liderazgo del usuario que se le intenta quitar el rol Líder.
     */
    private function submittedEvidenceUnderLeadership(User $leader): Evidence
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create();
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        return Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Submitted,
        ]);
    }

    public function test_removing_leader_role_is_blocked_when_user_has_pending_reviews(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $this->submittedEvidenceUnderLeadership($leader);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $leader])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasErrors('selectedRoles');

        $this->assertTrue($leader->fresh()->hasRole(RoleName::Leader));
    }

    public function test_removing_leader_role_is_allowed_when_no_pending_reviews(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $evidence = $this->submittedEvidenceUnderLeadership($leader);
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $leader])
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($leader->fresh()->hasRole(RoleName::Leader));
    }

    public function test_adding_a_role_is_never_blocked_by_pending_work(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('selectedRoles', [RoleName::Teacher->value, RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Teacher));
        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Coordination));
    }

    public function test_role_removal_guard_is_skipped_when_deactivating_the_user(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserForm::class, ['user' => $teacher])
            ->set('is_active', false)
            ->set('selectedRoles', [RoleName::Coordination->value])
            ->call('save')
            ->assertHasNoErrors();

        $teacher->refresh();
        $this->assertFalse($teacher->is_active);
        $this->assertFalse($teacher->hasRole(RoleName::Teacher));
    }

    public function test_deactivating_a_teacher_is_blocked_when_user_has_pending_evidences(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $teacher)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 evidencia pendiente'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 evidencia pendiente'));

        $this->assertTrue($teacher->fresh()->is_active);
    }

    public function test_deactivating_a_teacher_is_allowed_when_no_pending_evidences(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $teacher)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($teacher->fresh()->is_active);
    }

    public function test_deactivating_a_leader_is_blocked_when_user_has_pending_reviews(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $this->submittedEvidenceUnderLeadership($leader);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $leader)
            ->assertSet('deactivationError', fn ($message) => str_contains($message, '1 revisión pendiente'))
            ->assertDispatched('confirm-modal', fn ($name, $params) => str_contains($params['body'], '1 revisión pendiente'));

        $this->assertTrue($leader->fresh()->is_active);
    }

    public function test_deactivating_a_leader_is_allowed_when_no_pending_reviews(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $evidence = $this->submittedEvidenceUnderLeadership($leader);
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $leader)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertFalse($leader->fresh()->is_active);
    }

    public function test_reactivating_a_user_is_never_blocked_by_pending_work(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher, ['is_active' => false]);
        Evidence::factory()->create(['user_id' => $teacher->id, 'status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleActive', $teacher)
            ->assertSet('deactivationError', '')
            ->assertNotDispatched('confirm-modal');

        $this->assertTrue($teacher->fresh()->is_active);
    }

    public function test_user_search_by_name_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->userWithRole(RoleName::Teacher, ['name' => 'Andrés Peña']);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->set('search', 'andres pena')
            ->assertViewHas('users', fn ($users) => $users->total() === 1
                && $users->first()->name === 'Andrés Peña');
    }
}
