<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditLogIndex extends Component
{
    use HasStandardPagination, WithPagination;

    public string $userFilter = '';

    public string $actionFilter = '';

    public string $fromFilter = '';

    public string $toFilter = '';

    public function updating($property): void
    {
        if (in_array($property, ['userFilter', 'actionFilter', 'fromFilter', 'toFilter'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->actionFilter, fn ($q) => $q->where('action', $this->actionFilter))
            ->when($this->fromFilter, fn ($q) => $q->where('created_at', '>=', $this->fromFilter))
            ->when($this->toFilter, fn ($q) => $q->where('created_at', '<=', $this->toFilter.' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return view('livewire.audit.audit-log-index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
