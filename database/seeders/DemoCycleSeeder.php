<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\EvidenceVersion;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\Concerns\RefusesInProduction;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Ciclo de demostración para completar el recorrido de la directora: crea
 * 6 entregables de ejemplo (prefijo "[DEMO] ", único criterio de
 * idempotencia junto con firstOrCreate en cada paso — correr el seeder
 * dos veces no duplica nada) para el docente YA revisado
 * (config('seeding.demo_cycle_teacher_email'), nunca hardcodeado — sería
 * el correo real de una persona en el repositorio) y un líder sintético
 * nuevo (lider.demo@sigae-uts.local) con ámbito exclusivamente sobre el
 * componente/actividad/programa "[DEMO]" que este seeder también crea —
 * así nunca puede tocar, ni por accidente, los entregables reales que la
 * directora ya revisó (viven en una actividad distinta).
 *
 * Revertir: `php artisan demo:cycle-revert` (App\Console\Commands\DemoCycleRevert).
 */
class DemoCycleSeeder extends Seeder
{
    use RefusesInProduction;

    public const PREFIX = '[DEMO] ';

    public const DEMO_LEADER_EMAIL = 'lider.demo@sigae-uts.local';

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        $teacher = $this->resolveTeacher();
        $period = $this->resolveActivePeriod();
        $leader = $this->resolveDemoLeader();
        [$component, $activity, $programUnit] = $this->resolveDemoScope();

        TeacherAssignment::firstOrCreate([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ], [
            'assigned_hours' => 2,
        ]);

        Leadership::firstOrCreate([
            'user_id' => $leader->id,
            'activity_id' => $activity->id,
            'academic_period_id' => $period->id,
        ], [
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        // a) + f (obligatorio con peso): pendiente, sin ninguna carga.
        $pending = $this->demoDeliverable($period, $activity, 'Pendiente sin carga', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'due_at' => now()->addDays(10),
        ]);
        $this->ensureEvidence($pending, $teacher);

        // b) borrador: una versión en edición, nunca enviada.
        $draftDeliverable = $this->demoDeliverable($period, $activity, 'Borrador en progreso', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'due_at' => now()->addDays(10),
        ]);
        $draftEvidence = $this->ensureEvidence($draftDeliverable, $teacher);
        if ($draftEvidence->status === EvidenceStatus::Pending) {
            $version = $draftEvidence->startOrGetDraftVersion($teacher);
            $this->attachDemoFile($version, 'borrador-ejemplo.pdf');
        }

        // c) devuelto por el líder, con observación ligada a esa versión,
        // listo para reenviar (segunda versión en edición, sin enviar).
        $returnedDeliverable = $this->demoDeliverable($period, $activity, 'Devuelto, listo para reenviar', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'due_at' => now()->addDays(10),
        ]);
        $returnedEvidence = $this->ensureEvidence($returnedDeliverable, $teacher);
        if ($returnedEvidence->versions()->count() < 2) {
            $v1 = $returnedEvidence->startOrGetDraftVersion($teacher);
            $this->attachDemoFile($v1, 'version-1-ejemplo.pdf');
            $returnedEvidence->submitCurrentVersion();
            $returnedEvidence->returnCurrentReviewForAdjustment($leader, '[DEMO] Ajusta el documento y vuelve a enviarlo.');
            $returnedEvidence->startOrGetDraftVersion($teacher);
        }

        // d) vencido: fecha límite en el pasado, nunca se llegó a enviar.
        // Misma transición y mismo registro de auditoría que
        // evidences:mark-overdue, aplicados solo a esta fila (el comando
        // real, al correr después, ya no vuelve a tocarla: su condición
        // de estado deja de cumplirse).
        $overdueDeliverable = $this->demoDeliverable($period, $activity, 'Vencido', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'due_at' => now()->subDays(2),
        ]);
        $overdueEvidence = $this->ensureEvidence($overdueDeliverable, $teacher);
        if ($overdueEvidence->status === EvidenceStatus::Pending) {
            $overdueEvidence->update(['status' => EvidenceStatus::Expired]);
            AuditLog::record('evidence_marked_overdue', $overdueEvidence, [
                'previous_status' => EvidenceStatus::Pending->value,
                'due_at' => $overdueEvidence->deliverable->due_at->toIso8601String(),
            ]);
        }

        // e) exento, con justificación.
        $exemptDeliverable = $this->demoDeliverable($period, $activity, 'Exento con justificación', [
            'is_mandatory' => true,
            'due_at' => now()->addDays(10),
        ]);
        $exemptEvidence = $this->ensureEvidence($exemptDeliverable, $teacher);
        if ($exemptEvidence->status === EvidenceStatus::Pending) {
            $exemptEvidence->markExempt('[DEMO] Justificación de ejemplo para la prueba de usabilidad.');
        }

        // f) opcional: nunca entra al % de avance, sin importar su estado.
        $optionalDeliverable = $this->demoDeliverable($period, $activity, 'Opcional de ejemplo', [
            'is_mandatory' => false,
            'due_at' => now()->addDays(10),
        ]);
        $this->ensureEvidence($optionalDeliverable, $teacher);

        $this->command?->info('DemoCycleSeeder: 6 entregables [DEMO] listos para '.$teacher->email.'.');
    }

    private function resolveTeacher(): User
    {
        $email = config('seeding.demo_cycle_teacher_email');

        if (blank($email)) {
            throw new RuntimeException(
                'DEMO_CYCLE_TEACHER_EMAIL no está definida. Agrégala a tu .env (o al panel de variables '
                .'de entorno de Render) con el correo del docente ya revisado antes de correr este seeder.'
            );
        }

        $teacher = User::where('email', $email)->first();

        if (! $teacher) {
            throw new RuntimeException("No existe ningún usuario con el correo configurado en DEMO_CYCLE_TEACHER_EMAIL ({$email}).");
        }

        if (! $teacher->hasRole(RoleName::Teacher)) {
            throw new RuntimeException('El usuario de DEMO_CYCLE_TEACHER_EMAIL no tiene el rol Docente.');
        }

        return $teacher;
    }

    private function resolveActivePeriod(): AcademicPeriod
    {
        $period = AcademicPeriod::where('status', AcademicPeriodStatus::Active)->first();

        if (! $period) {
            throw new RuntimeException('No hay ningún periodo académico Activo — DemoCycleSeeder necesita uno para crear entregables dentro de plazo.');
        }

        return $period;
    }

    private function resolveDemoLeader(): User
    {
        $role = Role::where('name', RoleName::Leader->value)->first();

        $leader = User::firstOrCreate(
            ['email' => self::DEMO_LEADER_EMAIL],
            [
                'name' => self::PREFIX.'Líder de prueba',
                'document_number' => '9999999999',
                'password' => Hash::make(config('seeding.demo_cycle_leader_password') ?: str()->random(32)),
                'is_active' => true,
            ]
        );

        if (! $leader->roles->contains('id', $role->id)) {
            $leader->roles()->attach($role->id);
        }

        return $leader;
    }

    /**
     * @return array{0: Component, 1: Activity, 2: ProgramUnit}
     */
    private function resolveDemoScope(): array
    {
        $component = Component::firstOrCreate(['name' => self::PREFIX.'Componente de prueba']);
        $activity = Activity::firstOrCreate([
            'name' => self::PREFIX.'Ciclo de demostración',
            'component_id' => $component->id,
        ]);
        $programUnit = ProgramUnit::firstOrCreate(['name' => self::PREFIX.'Programa de prueba']);

        return [$component, $activity, $programUnit];
    }

    private function demoDeliverable(AcademicPeriod $period, Activity $activity, string $name, array $attributes): Deliverable
    {
        return Deliverable::firstOrCreate(
            ['name' => self::PREFIX.$name, 'academic_period_id' => $period->id],
            array_merge([
                'activity_id' => $activity->id,
                'periodicity_type' => PeriodicityType::Single,
                'allowed_evidence_types' => [EvidenceType::File->value],
                'allowed_file_types' => ['pdf'],
                'max_files' => 1,
                'max_file_size_mb' => 10,
                'opens_at' => now()->subDay(),
            ], $attributes)
        );
    }

    private function ensureEvidence(Deliverable $deliverable, User $teacher): Evidence
    {
        $deliverable->recipients()->syncWithoutDetaching([$teacher->id]);
        $deliverable->ensureEvidencesForRecipients([$teacher->id]);

        return $deliverable->evidences()->where('user_id', $teacher->id)->firstOrFail();
    }

    private function attachDemoFile(EvidenceVersion $version, string $name): void
    {
        $file = UploadedFile::fake()->create($name, 50, 'application/pdf');
        $storedPath = $file->store('evidence/'.$version->id, 'local');

        $version->files()->create([
            'original_name' => $name,
            'stored_name' => basename($storedPath),
            'disk_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file->getSize(),
        ]);
    }
}
