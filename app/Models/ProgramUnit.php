<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\OrdersNewestFirst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
