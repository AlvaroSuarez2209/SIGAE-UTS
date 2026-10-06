<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OrdersNewestFirst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramUnit extends Model
{
    use Auditable, HasFactory, OrdersNewestFirst;

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
}
