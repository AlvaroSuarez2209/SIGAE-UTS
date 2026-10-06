<?php

namespace App\Services\TeacherImport;

use App\Enums\RoleName;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Importación masiva de docentes (Administración > Usuarios > Importar
 * docentes). validateRows() solo lee y valida — no escribe nada en la base
 * de datos, es la vista previa. commit() procesa las filas ya validadas,
 * cada una en su propia transacción, para que un fallo a mitad de camino no
 * deje nada a medias ni bloquee el resto del lote.
 *
 * Decisiones de diseño (ver diagnóstico, prioridad 2 de la directora):
 * - `roles` del archivo solo acepta Docente y/o Líder — nunca Administrador,
 *   Coordinación ni Auditor. Un archivo nunca debe poder crear
 *   administradores, aunque esos roles sí existan en la tabla `roles`.
 * - `codigo_programa` valida contra `program_units.name` (ningún catálogo
 *   del sistema tiene un campo "código" propio), comparado normalizado
 *   (minúsculas + sin tildes) — mismo criterio que
 *   App\Rules\CaseAccentInsensitiveUnique.
 * - Una fila cuyo documento o correo ya existe en el sistema se trata como
 *   actualización, no como duplicado rechazado — salvo que documento y
 *   correo apunten a dos usuarios ya existentes distintos (conflicto real,
 *   si se rechaza). Si los datos entrantes son idénticos a los ya
 *   guardados, se cuenta como "omitida", no como "actualizada".
 */
class TeacherImportService
{
    public const MAX_ROWS = 500;

    // Las etiquetas legibles de estos 4 códigos viven en App\Enums\DocumentType
    // (reutilizado por UserForm, "Mi perfil" y AuditLogPresenter) — se
    // mantienen separados de esta lista a propósito: esta es la whitelist de
    // validación cruda del archivo, el enum es solo presentación.
    public const DOCUMENT_TYPES = ['CC', 'CE', 'TI', 'PA'];

    public const ACTIVE_VALUES = ['activo', 'inactivo'];

    /** @var array<int, RoleName> */
    public const ALLOWED_ROLES = [RoleName::Teacher, RoleName::Leader];

    public const REQUIRED_HEADERS = [
        'tipo_documento',
        'numero_documento',
        'nombre_completo',
        'correo_institucional',
        'codigo_programa',
        'roles',
        'estado',
    ];

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function validateRows(Collection $rows): array
    {
        $normalizedRows = $rows->values()->map(fn ($row) => $this->normalizeRawRow($row->toArray()));

        $documentCounts = $normalizedRows->countBy(fn ($row) => $this->normalizeExact($row['numero_documento']));
        $emailCounts = $normalizedRows->countBy(fn ($row) => $this->normalizeExact($row['correo_institucional']));

        $programUnits = ProgramUnit::query()->pluck('name', 'id');
        $roleLabels = Role::query()->pluck('label', 'name');

        return $normalizedRows
            ->map(fn ($data, $index) => $this->validateRow($index + 2, $data, $documentCounts, $emailCounts, $programUnits, $roleLabels))
            ->all();
    }

    private function normalizeRawRow(array $raw): array
    {
        return [
            'tipo_documento' => Str::upper(trim((string) ($raw['tipo_documento'] ?? ''))),
            'numero_documento' => trim((string) ($raw['numero_documento'] ?? '')),
            'nombre_completo' => trim((string) ($raw['nombre_completo'] ?? '')),
            'correo_institucional' => trim((string) ($raw['correo_institucional'] ?? '')),
            'codigo_programa' => trim((string) ($raw['codigo_programa'] ?? '')),
            'roles' => trim((string) ($raw['roles'] ?? '')),
            'estado' => Str::lower(trim((string) ($raw['estado'] ?? ''))),
        ];
    }

    private function validateRow(int $rowNumber, array $data, Collection $documentCounts, Collection $emailCounts, Collection $programUnits, Collection $roleLabels): array
    {
        $validator = Validator::make($data, [
            'tipo_documento' => ['required', Rule::in(self::DOCUMENT_TYPES)],
            'numero_documento' => ['required', 'string', 'max:50'],
            'nombre_completo' => ['required', 'string', 'max:255'],
            'correo_institucional' => ['required', 'email', 'max:255'],
            'codigo_programa' => ['required', 'string'],
            'roles' => ['required', 'string'],
            'estado' => ['required', Rule::in(self::ACTIVE_VALUES)],
        ]);

        $errors = $validator->fails() ? $validator->errors()->all() : [];

        if ($this->normalizeExact($data['numero_documento']) !== ''
            && ($documentCounts[$this->normalizeExact($data['numero_documento'])] ?? 0) > 1) {
            $errors[] = 'El número de documento está duplicado dentro del archivo.';
        }

        if ($this->normalizeExact($data['correo_institucional']) !== ''
            && ($emailCounts[$this->normalizeExact($data['correo_institucional'])] ?? 0) > 1) {
            $errors[] = 'El correo institucional está duplicado dentro del archivo.';
        }

        $programUnitId = null;

        if ($data['codigo_programa'] !== '') {
            $programUnitId = $this->matchByNormalizedName($programUnits, $data['codigo_programa']);

            if ($programUnitId === null) {
                $errors[] = "El código de programa \"{$data['codigo_programa']}\" no existe en los programas académicos registrados.";
            }
        }

        $resolvedRoleNames = [];

        if ($data['roles'] !== '') {
            foreach (explode(',', $data['roles']) as $piece) {
                $piece = trim($piece);

                if ($piece === '') {
                    continue;
                }

                $roleName = $this->matchAllowedRole($piece, $roleLabels);

                if ($roleName === null) {
                    $errors[] = "El rol \"{$piece}\" no es válido para esta importación (solo se permite Docente y/o Líder).";
                } elseif (! in_array($roleName, $resolvedRoleNames, true)) {
                    $resolvedRoleNames[] = $roleName;
                }
            }
        }

        $row = [
            'row_number' => $rowNumber,
            'data' => $data,
            'errors' => $errors,
            'action' => null,
            'existing_user_id' => null,
            'resolved_role_names' => $resolvedRoleNames,
            'resolved_program_unit_id' => $programUnitId,
            'is_active' => $data['estado'] === 'activo',
        ];

        if ($errors !== []) {
            $row['action'] = 'reject';

            return $row;
        }

        $this->resolveAction($row);

        return $row;
    }

    /**
     * Se llama tanto en la vista previa como de nuevo justo antes de
     * escribir (ver commit()) — entre una y otra, otro administrador pudo
     * haber creado un usuario conflictivo, así que no basta con confiar en
     * la acción resuelta en la vista previa.
     */
    private function resolveAction(array &$row): void
    {
        $data = $row['data'];

        $byDocument = User::where('document_number', $data['numero_documento'])->first();
        $byEmail = User::whereRaw('lower(email) = ?', [Str::lower($data['correo_institucional'])])->first();

        if ($byDocument && $byEmail && $byDocument->id !== $byEmail->id) {
            $row['errors'][] = 'El número de documento y el correo corresponden a usuarios distintos ya registrados.';
            $row['action'] = 'reject';

            return;
        }

        $existing = $byDocument ?: $byEmail;

        if (! $existing) {
            $row['action'] = 'create';

            return;
        }

        $row['existing_user_id'] = $existing->id;

        $currentRoleNames = $existing->roles->pluck('name')->sort()->values()->all();
        $incomingRoleNames = collect($row['resolved_role_names'])->sort()->values()->all();

        $noChanges = $existing->name === $data['nombre_completo']
            && $existing->document_type === $data['tipo_documento']
            && $existing->program_unit_id === $row['resolved_program_unit_id']
            && $existing->is_active === $row['is_active']
            && $currentRoleNames === $incomingRoleNames;

        $row['action'] = $noChanges ? 'skip' : 'update';
    }

    private function normalizeExact(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function normalizeLoose(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    private function matchByNormalizedName(Collection $namesById, string $value): ?int
    {
        $needle = $this->normalizeLoose($value);

        foreach ($namesById as $id => $name) {
            if ($this->normalizeLoose($name) === $needle) {
                return $id;
            }
        }

        return null;
    }

    private function matchAllowedRole(string $value, Collection $labelsByName): ?string
    {
        $needle = $this->normalizeLoose($value);

        foreach (self::ALLOWED_ROLES as $role) {
            $label = $labelsByName[$role->value] ?? $role->label();

            if ($this->normalizeLoose($label) === $needle || $this->normalizeLoose($role->value) === $needle) {
                return $role->value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  salida de validateRows()
     * @return array{created: int, updated: int, skipped: int, rejected: int, rejected_rows: array<int, array>}
     */
    public function commit(array $rows): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'rejected' => 0, 'rejected_rows' => []];

        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                $summary['rejected']++;
                $summary['rejected_rows'][] = $row;

                continue;
            }

            // Revalida contra el estado actual de la base de datos — no
            // contra la vista previa, que pudo quedar desactualizada.
            $this->resolveAction($row);

            if ($row['errors'] !== []) {
                $summary['rejected']++;
                $summary['rejected_rows'][] = $row;

                continue;
            }

            try {
                $action = DB::transaction(fn () => $this->commitRow($row));
            } catch (\Throwable) {
                $row['errors'][] = 'No se pudo guardar esta fila por un error inesperado.';
                $summary['rejected']++;
                $summary['rejected_rows'][] = $row;

                continue;
            }

            if ($action === 'skip') {
                $summary['skipped']++;

                continue;
            }

            if ($action === 'create') {
                $summary['created']++;
                // Fuera de la transacción a propósito: nunca disparar un
                // efecto externo (correo) dentro de una transacción que
                // podría revertirse.
                Password::sendResetLink(['email' => $row['data']['correo_institucional']]);
            } else {
                $summary['updated']++;
            }
        }

        return $summary;
    }

    private function commitRow(array $row): string
    {
        $data = $row['data'];

        $attributes = [
            'name' => $data['nombre_completo'],
            'document_number' => $data['numero_documento'],
            'document_type' => $data['tipo_documento'],
            'email' => $data['correo_institucional'],
            'program_unit_id' => $row['resolved_program_unit_id'],
            'is_active' => $row['is_active'],
        ];

        if ($row['existing_user_id']) {
            $user = User::findOrFail($row['existing_user_id']);
            $user->update($attributes);
            $action = 'update';
        } else {
            $attributes['password'] = Hash::make(Str::random(40));
            $user = User::create($attributes);
            $action = 'create';
        }

        $roleIds = Role::whereIn('name', $row['resolved_role_names'])->pluck('id');
        $user->roles()->sync($roleIds);

        return $action;
    }
}
