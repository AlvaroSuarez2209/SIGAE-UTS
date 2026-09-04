<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssignment extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'academic_period_id',
        'activity_id',
        'program_unit_id',
        'assigned_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_hours' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function programUnit(): BelongsTo
    {
        return $this->belongsTo(ProgramUnit::class);
    }
}
