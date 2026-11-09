<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use Auditable, HasActiveScope, HasFactory;

    protected $fillable = [
        'component_id',
        'subcomponent_id',
        'name',
        'description',
        'is_mandatory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function subcomponent(): BelongsTo
    {
        return $this->belongsTo(Subcomponent::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /**
     * Orden jerárquico para todos los selectores/listados de actividad
     * del sistema: componente A-Z, luego subcomponente A-Z (las
     * actividades sin subcomponente van al inicio de su componente —
     * se tratan como cadena vacía, que ordena antes que cualquier
     * letra), luego actividad A-Z, todo insensible a tildes, con
     * desempate final por id. Usa subconsultas en vez de un JOIN para
     * no chocar con columnas ambiguas (is_active/id existen también en
     * components y subcomponents) y para no tener que tocar los
     * where()/orWhere() que cada selector ya aplica sobre esta misma
     * consulta.
     */
    public function scopeOrderedByHierarchy($query)
    {
        return $query
            ->orderByRaw("translate(lower((select name from components where components.id = activities.component_id)), 'áéíóúñü', 'aeiounu') asc")
            ->orderByRaw("translate(lower(coalesce((select name from subcomponents where subcomponents.id = activities.subcomponent_id), '')), 'áéíóúñü', 'aeiounu') asc")
            ->orderByAccentInsensitive('activities.name')
            ->orderBy('activities.id');
    }
}
