<?php

namespace App\Livewire\Admin;

use App\Enums\EmploymentType;
use App\Models\EmploymentTypeHourLimit;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla sencilla para editar los topes semanales de horas por tipo de
 * vinculación — accesible a Administrador y Coordinación. Los 4 tipos de
 * vinculación son un conjunto fijo (ver migración
 * 2026_11_06_000003_create_employment_type_hour_limits_table), así que
 * esta pantalla solo edita filas existentes: nunca crea ni borra ninguna.
 */
#[Layout('layouts.app')]
#[Title('Topes de horas por vinculación')]
class EmploymentTypeHourLimitIndex extends Component
{
    /** @var array<int, string> */
    public array $weeklyHourLimits = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', EmploymentTypeHourLimit::class);

        $this->weeklyHourLimits = EmploymentTypeHourLimit::query()
            ->pluck('weekly_hour_limit', 'id')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    public function save(int $limitId): void
    {
        $limit = EmploymentTypeHourLimit::findOrFail($limitId);

        Gate::authorize('update', $limit);

        $this->validate([
            "weeklyHourLimits.{$limitId}" => ['required', 'integer', 'min:1'],
        ], [
            "weeklyHourLimits.{$limitId}.required" => 'El tope semanal de horas es obligatorio.',
            "weeklyHourLimits.{$limitId}.integer" => 'El tope semanal de horas debe ser un número entero.',
            "weeklyHourLimits.{$limitId}.min" => 'El tope semanal de horas debe ser mayor que cero.',
        ]);

        $limit->update(['weekly_hour_limit' => (int) $this->weeklyHourLimits[$limitId]]);

        session()->flash('status', 'Tope de horas actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.employment-type-hour-limit-index', [
            'limits' => EmploymentTypeHourLimit::orderBy('employment_type')->get(),
            'labels' => collect(EmploymentType::cases())->mapWithKeys(fn (EmploymentType $c) => [$c->value => $c->label()]),
        ]);
    }
}
