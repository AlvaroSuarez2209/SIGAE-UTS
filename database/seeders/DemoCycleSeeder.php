<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
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
 * Punto 10 amplía el recorrido con dos líderes sintéticos más
 * (lider.demo.b@sigae-uts.local, lider.demo.c@sigae-uts.local, mismo
 * criterio de contraseña por variable de entorno que el primero): B se
 * agrega como segundo líder vigente de la MISMA actividad existente (para
 * poder probar qué pasa cuando dos líderes comparten ámbito — ver
 * ReviewInboxTest::test_two_leaders_over_the_same_scope_both_see_it_until_one_decides()),
 * C recibe su propio ámbito exclusivo (otra actividad/programa "[DEMO]"
 * nuevos). También agrega una actividad "[DEMO]" sin ningún líder (para el
 * respaldo de Administrador del punto 7) y un compromiso transversal
 * "[DEMO]" con su propio entregable.
 *
 * Revertir: `php artisan demo:cycle-revert` (App\Console\Commands\DemoCycleRevert).
 */
class DemoCycleSeeder extends Seeder
{
    use RefusesInProduction;

    public const PREFIX = '[DEMO] ';

    public const DEMO_LEADER_EMAIL = 'lider.demo@sigae-uts.local';

    public const DEMO_LEADER_B_EMAIL = 'lider.demo.b@sigae-uts.local';

    public const DEMO_LEADER_C_EMAIL = 'lider.demo.c@sigae-uts.local';

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        $teacher = $this->resolveTeacher();
        $period = $this->resolveActivePeriod();
        $leader = $this->resolveDemoUser(self::DEMO_LEADER_EMAIL, 'Líder de prueba', '9999999999', 'demo_cycle_leader_password');
        $leaderB = $this->resolveDemoUser(self::DEMO_LEADER_B_EMAIL, 'Líder de prueba B', '9999999998', 'demo_cycle_leader_b_password');
        $leaderC = $this->resolveDemoUser(self::DEMO_LEADER_C_EMAIL, 'Líder de prueba C', '9999999997', 'demo_cycle_leader_c_password');
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

        // Punto 10: segundo líder vigente sobre la MISMA actividad — mismo
        // patrón exacto que el líder original (incluido activity_id, no
        // null), para que demo:cycle-revert siga limpiándolo con su mismo
        // filtro `Leadership::where('activity_id', ...)`.
        Leadership::firstOrCreate([
            'user_id' => $leaderB->id,
            'activity_id' => $activity->id,
            'academic_period_id' => $period->id,
        ], [
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        // a) + f (obligatorio con peso): pendiente, sin ninguna carga.
        // Dentro de plazo — se puede cargar y enviar con normalidad.
        $pending = $this->demoDeliverable($period, $activity, 'Pendiente sin carga', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $this->ensureEvidence($pending, $teacher);

        // b) borrador: una versión en edición, nunca enviada. Dentro de
        // plazo — se puede enviar.
        $draftDeliverable = $this->demoDeliverable($period, $activity, 'Borrador en progreso', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $draftEvidence = $this->ensureEvidence($draftDeliverable, $teacher);
        if ($draftEvidence->status === EvidenceStatus::Pending) {
            $version = $draftEvidence->startOrGetDraftVersion($teacher);
            $this->attachDemoFile($version, 'borrador-ejemplo.pdf');
        }

        // c) devuelto por el líder, con observación ligada a esa versión,
        // listo para reenviar (segunda versión en edición, sin enviar).
        // closes_at ya pasó a propósito (hace 7 días) — demuestra que una
        // devolución se puede corregir y reenviar fuera de plazo (ver
        // EvidenceWorkspace::submit(), excepción explícita para
        // reenvíos tras Review::Returned). due_at también en el pasado,
        // antes del cierre, para que las dos fechas queden coherentes
        // entre sí (closes_at nunca antes de due_at).
        $returnedDeliverable = $this->demoDeliverable($period, $activity, 'Devuelto, listo para reenviar', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'opens_at' => now()->subDays(20),
            'due_at' => now()->subDays(10),
            'closes_at' => now()->subDays(7),
        ]);
        $returnedEvidence = $this->ensureEvidence($returnedDeliverable, $teacher);
        if ($returnedEvidence->versions()->count() < 2) {
            $v1 = $returnedEvidence->startOrGetDraftVersion($teacher);
            $this->attachDemoFile($v1, 'version-1-ejemplo.pdf');
            $returnedEvidence->submitCurrentVersion();
            $returnedEvidence->returnCurrentReviewForAdjustment($leader, '[DEMO] Ajusta el documento y vuelve a enviarlo.');
            $returnedEvidence->startOrGetDraftVersion($teacher);
        }

        // d) vencido: due_at y closes_at en el pasado, pero la evidencia
        // se deja en Pendiente a propósito (sin devolución alguna) — no
        // se simula el paso de evidences:mark-overdue. Así, al intentar
        // enviarla, pasa isEditable() (Pendiente sí lo es) y llega al
        // chequeo de closes_at en EvidenceWorkspace::submit(), que la
        // bloquea con "El plazo de este entregable cerró el {fecha}" —
        // justo lo que hay que poder demostrar. Si se prefiere ver el
        // estado "Vencido" en pantalla, basta con correr
        // evidences:mark-overdue (o el cron del punto E) después de
        // sembrar: su condición (Pendiente/Borrador con due_at pasado)
        // la toma igual que a cualquier otra.
        $overdueDeliverable = $this->demoDeliverable($period, $activity, 'Vencido', [
            'is_mandatory' => true,
            'weight_percentage' => 25,
            'opens_at' => now()->subDays(15),
            'due_at' => now()->subDays(5),
            'closes_at' => now()->subDays(2),
        ]);
        $this->ensureEvidence($overdueDeliverable, $teacher);

        // e) exento, con justificación. Dentro de plazo.
        $exemptDeliverable = $this->demoDeliverable($period, $activity, 'Exento con justificación', [
            'is_mandatory' => true,
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $exemptEvidence = $this->ensureEvidence($exemptDeliverable, $teacher);
        if ($exemptEvidence->status === EvidenceStatus::Pending) {
            $exemptEvidence->markExempt('[DEMO] Justificación de ejemplo para la prueba de usabilidad.');
        }

        // f) opcional: nunca entra al % de avance, sin importar su estado.
        // Dentro de plazo.
        $optionalDeliverable = $this->demoDeliverable($period, $activity, 'Opcional de ejemplo', [
            'is_mandatory' => false,
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $this->ensureEvidence($optionalDeliverable, $teacher);

        // Punto 10: ámbito propio y exclusivo del líder C — programa y
        // actividad "[DEMO]" nuevos, distintos de los del líder original (y
        // de los del líder B, que comparte el original). Mismo componente
        // compartido: el componente no participa del emparejamiento de
        // ámbito (solo program_unit_id + activity_id + academic_period_id lo
        // hacen), así que no hace falta uno nuevo por cada actividad.
        [$programUnitC, $activityC] = $this->resolveSecondaryScope($component, 'Programa de Líder C', 'Actividad de Líder C');

        TeacherAssignment::firstOrCreate([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityC->id,
            'program_unit_id' => $programUnitC->id,
        ], [
            'assigned_hours' => 2,
        ]);

        Leadership::firstOrCreate([
            'user_id' => $leaderC->id,
            'activity_id' => $activityC->id,
            'academic_period_id' => $period->id,
        ], [
            'program_unit_id' => $programUnitC->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        // Una evidencia exenta en el ámbito de cada líder nuevo. La de la
        // actividad original cae dentro del ámbito de B (ahora segundo
        // líder vigente ahí); la de la actividad C, en el de C. Esta
        // segunda también sirve para demostrar el 403: B NO tiene ningún
        // líderazgo sobre la actividad de C, así que cualquier intento suyo
        // de abrir o revisar esta evidencia debe rechazarse.
        $exemptBDeliverable = $this->demoDeliverable($period, $activity, 'Exenta en ámbito de Líder B', [
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $exemptBEvidence = $this->ensureEvidence($exemptBDeliverable, $teacher);
        if ($exemptBEvidence->status === EvidenceStatus::Pending) {
            $exemptBEvidence->markExempt('[DEMO] Justificación de ejemplo, ámbito del líder B.');
        }

        $exemptCDeliverable = $this->demoDeliverable($period, $activityC, 'Exenta en ámbito de Líder C', [
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $exemptCEvidence = $this->ensureEvidence($exemptCDeliverable, $teacher);
        if ($exemptCEvidence->status === EvidenceStatus::Pending) {
            $exemptCEvidence->markExempt('[DEMO] Justificación de ejemplo, ámbito del líder C.');
        }

        // Punto 7 / punto 10: actividad "[DEMO]" sin ningún Leadership —
        // demuestra el respaldo de Administrador para actividades sin
        // ningún líder vigente en este momento (Evidence::isReviewableBy()).
        [$programUnitOrphan, $activityOrphan] = $this->resolveSecondaryScope($component, 'Programa sin líder', 'Actividad sin líder');

        TeacherAssignment::firstOrCreate([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityOrphan->id,
            'program_unit_id' => $programUnitOrphan->id,
        ], [
            'assigned_hours' => 2,
        ]);

        // Punto 1 (revisión de perfil Coordinación): ENVIADA, no solo
        // Pendiente — así aparece de una vez en la bandeja de Coordinación/
        // Administrador (filtrada por status Submitted), demostrando el
        // respaldo del punto 7 sin necesitar que el docente la envíe antes.
        $orphanDeliverable = $this->demoDeliverable($period, $activityOrphan, 'Sin ningún líder vigente', [
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $orphanEvidence = $this->ensureEvidence($orphanDeliverable, $teacher);
        $this->submitDemoEvidence($orphanEvidence, $teacher, 'sin-lider-ejemplo.pdf');

        // Compromiso transversal "[DEMO]" con su propio entregable — ya
        // cubierto por isCrossCutting()/isReviewableBy() (Administrador y,
        // desde el punto 1, también Coordinación), esto solo lo hace
        // visible también en el recorrido de demostración. También
        // ENVIADA, mismo motivo que la de arriba.
        $commitment = CrossCuttingCommitment::firstOrCreate([
            'name' => self::PREFIX.'Compromiso transversal',
        ], [
            'is_active' => true,
        ]);

        $transversalDeliverable = Deliverable::firstOrCreate([
            'name' => self::PREFIX.'Entregable transversal',
            'academic_period_id' => $period->id,
        ], [
            'cross_cutting_commitment_id' => $commitment->id,
            'periodicity_type' => PeriodicityType::Single,
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf'],
            'max_files' => 1,
            'max_file_size_mb' => 10,
            'opens_at' => now()->subDay(),
            'due_at' => now()->addDays(10),
            'closes_at' => now()->addDays(30),
        ]);
        $transversalEvidence = $this->ensureEvidence($transversalDeliverable, $teacher);
        $this->submitDemoEvidence($transversalEvidence, $teacher, 'transversal-ejemplo.pdf');

        $this->command?->info('DemoCycleSeeder: 6 entregables [DEMO] listos para '.$teacher->email.', más el ámbito de los líderes B y C.');
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

    private function resolveDemoUser(string $email, string $name, string $documentNumber, string $passwordConfigKey): User
    {
        $role = Role::where('name', RoleName::Leader->value)->first();

        $leader = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => self::PREFIX.$name,
                'document_number' => $documentNumber,
                'password' => Hash::make(config("seeding.{$passwordConfigKey}") ?: str()->random(32)),
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

    /**
     * Punto 10: un programa + actividad "[DEMO]" propios, bajo el mismo
     * componente compartido — program_unit_id es parte de la clave de
     * ámbito (TeacherAssignment/Leadership), así que cada ámbito nuevo
     * necesita su propio ProgramUnit aunque reutilice el Component.
     *
     * @return array{0: ProgramUnit, 1: Activity}
     */
    private function resolveSecondaryScope(Component $component, string $programUnitName, string $activityName): array
    {
        $programUnit = ProgramUnit::firstOrCreate(['name' => self::PREFIX.$programUnitName]);
        $activity = Activity::firstOrCreate([
            'name' => self::PREFIX.$activityName,
            'component_id' => $component->id,
        ]);

        return [$programUnit, $activity];
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

    /**
     * Envía una evidencia Pendiente de una sola vez (un archivo + enviar)
     * — idempotente igual que el resto del seeder: si ya no está
     * Pendiente (ya se envió en una corrida anterior), no hace nada.
     */
    private function submitDemoEvidence(Evidence $evidence, User $teacher, string $fileName): void
    {
        if ($evidence->status !== EvidenceStatus::Pending) {
            return;
        }

        $version = $evidence->startOrGetDraftVersion($teacher);
        $this->attachDemoFile($version, $fileName);
        $evidence->submitCurrentVersion();
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
