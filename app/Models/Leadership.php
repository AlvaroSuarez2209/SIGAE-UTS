<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leadership extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'activity_id',
        'program_unit_id',
        'academic_period_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function programUnit(): BelongsTo
    {
        return $this->belongsTo(ProgramUnit::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function isActiveOn(\DateTimeInterface $date): bool
    {
        return ! $this->starts_at->gt($date) && (! $this->ends_at || ! $this->ends_at->lt($date));
    }
}
