<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OrdersNewestFirst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
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

    public function subcomponents(): HasMany
    {
        return $this->hasMany(Subcomponent::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
