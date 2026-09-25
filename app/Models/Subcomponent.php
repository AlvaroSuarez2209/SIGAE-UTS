<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OrdersNewestFirst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcomponent extends Model
{
    use Auditable, HasFactory, OrdersNewestFirst;

    protected $fillable = [
        'component_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
