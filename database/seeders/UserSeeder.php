<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        $users = [
            ['name' => 'Administrador', 'email' => 'admin@uts.edu.co', 'document_number' => '1000000001', 'roles' => [RoleName::Administrator->value], 'is_active' => true, 'password' => 'Colombia2026*'],

            // Cuentas de demostración — desactivadas junto con
            // TeacherAssignmentSeeder, LeadershipSeeder y EvidenceSeeder,
            // que dependen de estos mismos correos. Reactivar las 4 juntas
            // si se necesita un seed completo de demostración.
            // ['name' => 'Carlos Coordinador', 'email' => 'coordinacion@sigae.local', 'document_number' => '1000000002', 'roles' => [RoleName::Coordination->value], 'is_active' => true, 'password' => 'password'],
            // ['name' => 'Laura Líder', 'email' => 'lider1@sigae.local', 'document_number' => '1000000003', 'roles' => [RoleName::Leader->value, RoleName::Teacher->value], 'is_active' => true, 'password' => 'password'],
            // ['name' => 'Diego Docente', 'email' => 'docente1@sigae.local', 'document_number' => '1000000004', 'roles' => [RoleName::Teacher->value], 'is_active' => true, 'password' => 'password'],
            // ['name' => 'Elena Docente', 'email' => 'docente2@sigae.local', 'document_number' => '1000000005', 'roles' => [RoleName::Teacher->value], 'is_active' => true, 'password' => 'password'],
            // ['name' => 'Felipe Docente Inactivo', 'email' => 'docente3@sigae.local', 'document_number' => '1000000006', 'roles' => [RoleName::Teacher->value], 'is_active' => false, 'password' => 'password'],
            // ['name' => 'Gloria Auditora', 'email' => 'auditor@sigae.local', 'document_number' => '1000000007', 'roles' => [RoleName::Auditor->value], 'is_active' => true, 'password' => 'password'],
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
