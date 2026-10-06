<?php

namespace App\Livewire\Reviews;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AcademicPeriod;
use App\Models\Evidence;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de solo lectura de evidencias Exentas — nunca una bandeja de
 * acción (sin aprobar/devolver/eximir aquí, eso sigue viviendo en
 * ReviewShow/EvidenceWorkspace).
 * Responde al hallazgo de que, antes de esto, una exención solo se
 * avisaba por correo (EvidenceExemptedForLeaderNotification) — en
 * pantalla, un Líder/Coordinación no tenía dónde verla.
 *
 * Para el Líder, reutiliza tal cual el scope `reviewableBy()` que ya usa
 * ReviewInbox (mismo criterio de vigencia que `User::canLeadAssignment()`).
 * Pero ese scope nunca incluye a Coordinación, y a Administrador solo lo
 * deja ver transversales — correcto para una bandeja de ACCIÓN (Review*),
 * donde esos roles solo respaldan huecos puntuales, pero no para este
 * listado de solo CONSULTA: Coordinación y Administrador necesitan ver
 * cualquier exención de cualquier actividad para hacer seguimiento global,
 * así que para ellos no se acota por ámbito en absoluto.
 */
#[Layout('layouts.app')]
#[Title('Evidencias exentas')]
class ExemptEvidenceIndex extends Component
{
    use HasStandardPagination, WithPagination;

    #[Url(as: 'period')]
    public ?int $periodFilter = null;

    public function mount(): void
    {
        $this->periodFilter ??= AcademicPeriod::where('status', 'active')->value('id');
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $exempt = Evidence::query()
            ->where('evidences.status', EvidenceStatus::Exempt)
            ->when(
                ! $user->hasAnyRole([RoleName::Administrator, RoleName::Coordination]),
                fn ($query) => $query->reviewableBy($user)
            )
            ->with(['deliverable.activity.component', 'deliverable.crossCuttingCommitment', 'user'])
            ->when($this->periodFilter, fn ($query) => $query
                ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $this->periodFilter))
            )
            // `updated_at` es el momento de la exención: markExempt() es la
            // última escritura real sobre una evidencia Exenta (nada la
            // vuelve a tocar hasta que se revierta), así que no hace falta
            // una consulta aparte a audit_logs solo para esta fecha.
            ->orderByDesc('evidences.updated_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.reviews.exempt-evidence-index', [
            'exempt' => $exempt,
            'periods' => AcademicPeriod::orderByDesc('start_date')->get(),
        ]);
    }
}
