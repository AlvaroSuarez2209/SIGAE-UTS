<?php

namespace Tests\Unit\Audit;

use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Evidence;
use App\Models\User;
use App\Services\Audit\AuditLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_translates_known_actions(): void
    {
        $this->assertEquals('Inicio de sesión', AuditLogPresenter::actionLabel('login'));
        $this->assertEquals('Marcada como exenta', AuditLogPresenter::actionLabel('evidence_marked_exempt'));
    }

    public function test_falls_back_to_the_raw_value_for_an_unmapped_action(): void
    {
        $this->assertEquals('algo_nuevo', AuditLogPresenter::actionLabel('algo_nuevo'));
    }

    public function test_action_options_keeps_the_raw_value_as_the_option_value(): void
    {
        $options = AuditLogPresenter::actionOptions(['login', 'created']);

        $this->assertEquals([
            'login' => 'Inicio de sesión',
            'created' => 'Creación',
        ], $options);
    }

    public function test_translates_known_model_names(): void
    {
        $this->assertEquals('Usuario', AuditLogPresenter::auditableLabel(User::class));
        $this->assertEquals('Componente', AuditLogPresenter::auditableLabel(Component::class));
    }

    public function test_falls_back_to_the_class_basename_for_an_unmapped_model(): void
    {
        $this->assertEquals('AuditLog', AuditLogPresenter::auditableLabel(AuditLog::class));
    }

    public function test_is_active_change_reads_as_account_activation_for_a_user(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'metadata' => ['changes' => ['is_active' => false]],
            'created_at' => now(),
        ]);

        $this->assertEquals('Cuenta desactivada', AuditLogPresenter::describeChanges($log));
    }

    public function test_is_active_change_reads_generically_for_a_catalog(): void
    {
        $component = Component::factory()->create();
        $log = AuditLog::create([
            'action' => 'updated',
            'auditable_type' => Component::class,
            'auditable_id' => $component->id,
            'metadata' => ['changes' => ['is_active' => true]],
            'created_at' => now(),
        ]);

        $this->assertEquals('Activado', AuditLogPresenter::describeChanges($log));
    }

    public function test_unmapped_field_falls_back_to_a_generic_field_changed_to_value_sentence(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'metadata' => ['changes' => ['some_future_field' => 'nuevo valor']],
            'created_at' => now(),
        ]);

        $this->assertEquals('some_future_field cambió a nuevo valor', AuditLogPresenter::describeChanges($log));
    }

    public function test_mapped_field_uses_its_spanish_label_in_the_generic_sentence(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'metadata' => ['changes' => ['email' => 'nuevo@sigae.local']],
            'created_at' => now(),
        ]);

        $this->assertEquals('Correo electrónico cambió a nuevo@sigae.local', AuditLogPresenter::describeChanges($log));
    }

    public function test_evidence_status_enum_value_is_translated_not_shown_raw(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'auditable_type' => Evidence::class,
            'metadata' => ['changes' => ['status' => 'exempt']],
            'created_at' => now(),
        ]);

        $this->assertEquals('Estado cambió a Exento', AuditLogPresenter::describeChanges($log));
    }

    public function test_academic_period_status_enum_value_is_translated_using_its_own_enum(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'auditable_type' => AcademicPeriod::class,
            'metadata' => ['changes' => ['status' => 'active']],
            'created_at' => now(),
        ]);

        // 'active' significa algo distinto según el modelo: aquí "Activo"
        // (AcademicPeriodStatus), no "Sí"/booleano ni el "Activado" de is_active.
        $this->assertEquals('Estado cambió a Activo', AuditLogPresenter::describeChanges($log));
    }

    public function test_review_decision_enum_value_is_translated(): void
    {
        $log = AuditLog::create([
            'action' => 'created',
            'metadata' => ['changes' => ['decision' => 'returned']],
            'created_at' => now(),
        ]);

        $this->assertEquals('Decisión cambió a Devuelto', AuditLogPresenter::describeChanges($log));
    }

    public function test_boolean_values_read_as_si_no_in_the_generic_sentence(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'metadata' => ['changes' => ['is_mandatory' => true]],
            'created_at' => now(),
        ]);

        $this->assertEquals('Obligatorio cambió a Sí', AuditLogPresenter::describeChanges($log));
    }

    public function test_redacted_password_reads_as_password_updated_without_exposing_it(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'metadata' => ['redacted_fields' => ['password']],
            'created_at' => now(),
        ]);

        $this->assertEquals('Contraseña actualizada', AuditLogPresenter::describeChanges($log));
    }

    public function test_remember_token_never_appears_even_when_redacted(): void
    {
        $log = AuditLog::create([
            'action' => 'updated',
            'metadata' => ['redacted_fields' => ['remember_token']],
            'created_at' => now(),
        ]);

        $this->assertEquals('', AuditLogPresenter::describeChanges($log));
    }

    public function test_no_metadata_produces_an_empty_detail(): void
    {
        $log = AuditLog::create([
            'action' => 'login',
            'created_at' => now(),
        ]);

        $this->assertEquals('', AuditLogPresenter::describeChanges($log));
    }
}
