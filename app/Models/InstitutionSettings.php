<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Fila única (id = 1, forzado también con un CHECK en la migración) con el
 * nombre y los logos institucionales configurables desde Administración —
 * ver docs del diagnóstico de la Prioridad 3.
 *
 * Dos logos separados, no uno reutilizado: el real tiene dos recortes
 * visuales distintos (apaisado para el login, cuadrado para sidebar/PDF/
 * correos — ver public/images/logo/README.md). `logo_login_path` /
 * `logo_mark_path` en null significa "sin logo personalizado para esa
 * superficie — usar los archivos estáticos de public/images/logo/", ver
 * guest.blade.php y logo-mark.blade.php, que hacen ese fallback cada uno
 * por su lado.
 */
class InstitutionSettings extends Model
{
    use Auditable, HasFactory;

    public const DEFAULT_NAME = 'Institución Universitaria Tecnológica de Santander';

    protected $fillable = [
        'name',
        'logo_login_path',
        'logo_mark_path',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * La única fila real del sistema. firstOrCreate() en vez de find(1) a
     * secas: defensivo ante una base de datos a la que, por lo que sea
     * (migración revertida a mano, entorno nuevo sin seed), le falte la
     * fila sembrada por la migración.
     */
    public static function current(): self
    {
        return static::query()->find(1) ?? static::query()->create([
            'id' => 1,
            'name' => self::DEFAULT_NAME,
        ]);
    }

    // --- Logo del login (apaisado, guest.blade.php) ---

    public function hasCustomLoginLogo(): bool
    {
        return filled($this->logo_login_path) && Storage::disk('institution')->exists($this->logo_login_path);
    }

    public function loginLogoUrl(): ?string
    {
        return $this->hasCustomLoginLogo() ? Storage::disk('institution')->url($this->logo_login_path) : null;
    }

    // --- Logo de marca (cuadrado, sidebar/PDF/correos) ---

    public function hasCustomMarkLogo(): bool
    {
        return filled($this->logo_mark_path) && Storage::disk('institution')->exists($this->logo_mark_path);
    }

    public function markLogoUrl(): ?string
    {
        return $this->hasCustomMarkLogo() ? Storage::disk('institution')->url($this->logo_mark_path) : null;
    }

    /**
     * Ruta real en disco del logo de marca — la necesita el PDF (dompdf
     * incrusta por ruta de archivo, no por URL HTTP), nunca la vista web.
     */
    public function markLogoDiskPath(): ?string
    {
        return $this->hasCustomMarkLogo() ? Storage::disk('institution')->path($this->logo_mark_path) : null;
    }

    public function resetToDefaults(): void
    {
        foreach ([$this->logo_login_path, $this->logo_mark_path] as $path) {
            if ($path) {
                Storage::disk('institution')->delete($path);
            }
        }

        $this->update([
            'name' => self::DEFAULT_NAME,
            'logo_login_path' => null,
            'logo_mark_path' => null,
        ]);
    }
}
