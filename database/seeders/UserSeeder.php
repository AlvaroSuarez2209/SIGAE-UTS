<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * La contraseña del Administrador inicial nunca vive en el
     * repositorio (ver auditoría de seguridad: admin@uts.edu.co /
     * ***REMOVED*** estuvo en texto plano aquí, en README.md y en
     * docs/manual-tecnico.md). Sin ADMIN_INITIAL_PASSWORD definida, este
     * seeder falla con un mensaje claro en vez de caer a cualquier valor
     * por defecto — local: en tu .env; Render: en el panel de variables
     * de entorno del servicio.
     */
    public function run(): void
    {
        $adminPassword = config('seeding.admin_initial_password');

        if (blank($adminPassword)) {
            throw new RuntimeException(
                'ADMIN_INITIAL_PASSWORD no está definida. Agrégala a tu .env local '
                .'(o al panel de variables de entorno de Render) antes de sembrar el Administrador inicial.'
            );
        }

        $roles = Role::pluck('id', 'name');

        $users = [
            ['name' => 'Administrador', 'email' => 'admin@uts.edu.co', 'document_number' => '1000000001', 'roles' => [RoleName::Administrator->value], 'is_active' => true, 'password' => $adminPassword],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'document_number' => $data['document_number'],
                    'password' => Hash::make($data['password']),
                    'is_active' => $data['is_active'],
                ]
            );

            $user->roles()->sync(collect($data['roles'])->map(fn ($role) => $roles[$role])->all());
        }
    }
}
