<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\PasswordPolicy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UserForm, Profile y ResetPassword comparten PasswordPolicy::rules() (ver
 * su docblock) — antes de la auditoría de seguridad, UserForm mantenía su
 * propia regla desactualizada (solo min:8, sin mayúscula/minúscula/número).
 * En vez de repetir a mano la lista de requisitos, este test deriva el
 * resultado esperado directamente de PasswordPolicy::rules() y verifica que
 * las 3 pantallas reales produzcan ese mismo resultado — así detecta si
 * alguna vuelve a desviarse con una regla propia en el futuro.
 */
class PasswordPolicyConsistencyTest extends TestCase
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

    private function isValidUnderPolicy(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => PasswordPolicy::rules()]
        )->passes();
    }

    public static function borderlinePasswords(): array
    {
        return [
            'sin mayúscula' => ['clave123!'],
            'sin minúscula' => ['CLAVE123!'],
            'sin número' => ['ClaveClave!'],
            'sin símbolo' => ['ClaveClave123'],
            'muy corta' => ['Cl1!'],
            'cumple todos los requisitos' => ['ClaveValida123!'],
        ];
    }

    #[DataProvider('borderlinePasswords')]
    public function test_user_form_matches_the_shared_policy(string $password): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('name', 'Nuevo Docente')
            ->set('email', 'nuevo.docente@sigae.local')
            ->set('password', $password)
            ->set('selectedRoles', [RoleName::Teacher->value])
            ->call('save');

        $this->assertEquals(
            $this->isValidUnderPolicy($password),
            User::where('email', 'nuevo.docente@sigae.local')->exists()
        );
    }

    #[DataProvider('borderlinePasswords')]
    public function test_profile_matches_the_shared_policy(string $password): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['password' => 'clave-original-A1']);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('current_password', 'clave-original-A1')
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('savePassword');

        $this->assertEquals(
            $this->isValidUnderPolicy($password),
            Hash::check($password, $user->fresh()->password)
        );
    }

    #[DataProvider('borderlinePasswords')]
    public function test_reset_password_matches_the_shared_policy(string $password): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('resetPassword');

        $this->assertEquals(
            $this->isValidUnderPolicy($password),
            Hash::check($password, $user->fresh()->password)
        );
    }
}
