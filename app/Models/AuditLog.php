<?php

namespace App\Models;

use App\Services\Audit\AuditLogPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bitácora de acciones críticas. Es de solo lectura desde la aplicación:
 * no existe (ni debe existir) ninguna pantalla o ruta que permita editar o
 * borrar un registro ya creado — solo AuditLog::record() puede escribir.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function auditableLabel(): string
    {
        if (! $this->auditable_type) {
            return '—';
        }

        $type = AuditLogPresenter::auditableLabel($this->auditable_type);

        if (! $this->auditable) {
            return "{$type} #{$this->auditable_id} (eliminado)";
        }

        $name = $this->auditable->name ?? null;

        return $name ? "{$type}: {$name}" : "{$type} #{$this->auditable_id}";
    }

    public static function record(string $action, ?Model $auditable = null, array $metadata = []): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
