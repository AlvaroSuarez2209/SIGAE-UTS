<?php

namespace App\Services\AdminImport;

use App\Enums\RoleName;
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
 * Importación masiva propia del Administrador (Administración > Usuarios >
 * Importar administradores/coordinadores) — revisión de la directora,
 * punto 7. A diferencia de App\Services\TeacherImport\TeacherImportService
 * (Docente/Líder, con tipo de documento y programa de adscripción), esta
 * plantilla solo acepta Administrador y/o Coordinación, que no necesitan
 * ninguno de esos dos datos — por eso es una plantilla y un servicio
 * separados, no una reutilización parametrizada del existente.
 *
 * Mismo patrón de resolveAction()/commit() que TeacherImportService, pero
 * emparejando solo por correo (document_number no aplica a estos roles).
 */
class AdminImportService
{
    public const MAX_ROWS = 500;

    public const ACTIVE_VALUES = ['activo', 'inactivo'];

    /** @var array<int, RoleName> */
    public const ALLOWED_ROLES = [RoleName::Administrator, RoleName::Coordination];

    public const REQUIRED_HEADERS = [
        'nombre_completo',
        'correo_institucional',
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

        $emailCounts = $normalizedRows->countBy(fn ($row) => $this->normalizeExact($row['correo_institucional']));

        $roleLabels = Role::query()->pluck('label', 'name');

        return $normalizedRows
            ->map(fn ($data, $index) => $this->validateRow($index + 2, $data, $emailCounts, $roleLabels))
            ->all();
    }

    private function normalizeRawRow(array $raw): array
    {
        return [
            'nombre_completo' => trim((string) ($raw['nombre_completo'] ?? '')),
            'correo_institucional' => trim((string) ($raw['correo_institucional'] ?? '')),
            'roles' => trim((string) ($raw['roles'] ?? '')),
            'estado' => Str::lower(trim((string) ($raw['estado'] ?? ''))),
        ];
    }

    private function validateRow(int $rowNumber, array $data, Collection $emailCounts, Collection $roleLabels): array
    {
        $validator = Validator::make($data, [
            'nombre_completo' => ['required', 'string', 'max:255'],
            'correo_institucional' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'string'],
            'estado' => ['required', Rule::in(self::ACTIVE_VALUES)],
        ]);

        $errors = $validator->fails() ? $validator->errors()->all() : [];

        if ($this->normalizeExact($data['correo_institucional']) !== ''
            && ($emailCounts[$this->normalizeExact($data['correo_institucional'])] ?? 0) > 1) {
            $errors[] = 'El correo institucional está duplicado dentro del archivo.';
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
                    $errors[] = "El rol \"{$piece}\" no es válido para esta importación (solo se permite Administrador y/o Coordinación).";
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
     * escribir (ver commit()), igual que TeacherImportService::resolveAction().
     */
    private function resolveAction(array &$row): void
    {
        $data = $row['data'];

        $existing = User::whereRaw('lower(email) = ?', [Str::lower($data['correo_institucional'])])->first();

        if (! $existing) {
            $row['action'] = 'create';

            return;
        }

        $row['existing_user_id'] = $existing->id;

        $currentRoleNames = $existing->roles->pluck('name')->sort()->values()->all();
        $incomingRoleNames = collect($row['resolved_role_names'])->sort()->values()->all();

        $noChanges = $existing->name === $data['nombre_completo']
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
     * @return array{created: int, updated: int, skipped: int, rejected: int, rejected_rows: array}
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

            if ($row['action'] === 'skip') {
                $summary['skipped']++;

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
            'email' => $data['correo_institucional'],
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
