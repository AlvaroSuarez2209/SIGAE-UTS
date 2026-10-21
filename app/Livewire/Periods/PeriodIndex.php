<?php

namespace App\Livewire\Periods;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use App\Rules\CaseAccentInsensitiveUnique;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Periodos')]
class PeriodIndex extends Component
{
    public bool $showModal = false;

    public ?AcademicPeriod $editing = null;

    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public bool $periodLocked = false;

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'start_date', 'end_date', 'periodLocked']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(AcademicPeriod $period): void
    {
        $this->resetValidation();
        $this->editing = $period;
        $this->name = $period->name;
        $this->start_date = $period->start_date->toDateString();
        $this->end_date = $period->end_date->toDateString();
        // RF-010: Cerrado/Archivado es modo consulta completo (ningún
        // campo, no solo las fechas) — a diferencia de Activo, que solo
        // bloquea fechas (ver $datesLocked en save()). Esta propiedad es
        // solo para la interfaz (deshabilitar campos, ocultar "Guardar");
        // save() nunca confía en ella para decidir si de verdad bloquea
        // la escritura, porque es una propiedad pública de Livewire y
        // viaja en cada request — se vuelve a calcular ahí directo desde
        // $this->editing->status, que Livewire siempre rehidrata fresco
        // desde la base de datos.
        $this->periodLocked = in_array($period->status, [AcademicPeriodStatus::Closed, AcademicPeriodStatus::Archived], true);
        $this->showModal = true;
    }

    /**
     * Cancelar, clic fuera de la tarjeta o Escape convergen aquí — nunca
     * deben dejar un error de una validación anterior visible la próxima
     * vez que se abra el modal (ver [[project-livewire4-gotchas]]).
     */
    public function closeModal(): void
    {
        $this->reset(['editing', 'name', 'start_date', 'end_date', 'periodLocked']);
        $this->resetValidation();
        $this->showModal = false;
    }

    public function save(): void
    {
        // RF-010, modo consulta real: recalculado aquí directo desde
        // $this->editing->status (nunca desde $this->periodLocked, una
        // propiedad pública que un cliente manipulado podría enviar en
        // false) — ni se valida ni se llama a update() para un periodo
        // Cerrado o Archivado, sin importar qué haya mandado el navegador.
        if ($this->editing && in_array($this->editing->status, [AcademicPeriodStatus::Closed, AcademicPeriodStatus::Archived], true)) {
            $this->addError('name', 'Este periodo está cerrado o archivado y no se puede modificar.');

            return;
        }

        $datesLocked = $this->editing && $this->editing->status !== AcademicPeriodStatus::Planning;

        $rules = [
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('academic_periods'))->ignore($this->editing)],
        ];

        if (! $datesLocked) {
            $rules['start_date'] = ['required', 'date'];
            $rules['end_date'] = ['required', 'date', 'after_or_equal:start_date'];
        }

        $data = $this->validate($rules);

        if ($this->editing) {
            $this->editing->update($data);
        } else {
            AcademicPeriod::create($data + ['status' => AcademicPeriodStatus::Planning]);
        }

        $this->showModal = false;
    }

    public function activate(AcademicPeriod $period): void
    {
        if ($period->status === AcademicPeriodStatus::Planning) {
            $period->update(['status' => AcademicPeriodStatus::Active]);
        }
    }

    public function close(AcademicPeriod $period): void
    {
        if ($period->status === AcademicPeriodStatus::Active) {
            $period->update(['status' => AcademicPeriodStatus::Closed]);
        }
    }

    public function archive(AcademicPeriod $period): void
    {
        if ($period->status === AcademicPeriodStatus::Closed) {
            $period->update(['status' => AcademicPeriodStatus::Archived]);
        }
    }

    public function reopen(AcademicPeriod $period): void
    {
        // Excepcional: reabrir un periodo cerrado. Se debe registrar en la
        // bitácora de auditoría cuando el módulo 10 esté implementado.
        if ($period->status === AcademicPeriodStatus::Closed) {
            $period->update(['status' => AcademicPeriodStatus::Active]);
        }
    }

    public function render()
    {
        return view('livewire.periods.period-index', [
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
