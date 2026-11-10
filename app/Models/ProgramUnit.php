<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramUnit extends Model
{
    use Auditable, HasActiveScope, HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function leaderships(): HasMany
    {
        return $this->hasMany(Leadership::class);
    }

    /**
     * Punto 2 (borrado real de catálogo): un usuario puede quedar
     * asignado a este programa directamente (users.program_unit_id,
     * nullOnDelete) sin que exista ninguna asignación docente ni
     * liderazgo todavía — también debe bloquear el borrado.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
