<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enlace de referencia (punto 5): solo http/https, nunca javascript:,
 * ftp: ni texto libre. Laravel's `url` rule por sí solo
 * acepta cualquier esquema con forma de URL (incluido javascript://),
 * así que no basta — aquí se exige explícitamente el esquema además de
 * la forma general de URL.
 */
class HttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^https?:\/\//i', $value)) {
            $fail('El campo :attribute debe ser un enlace http:// o https:// válido.');

            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('El campo :attribute debe ser un enlace válido.');
        }
    }
}
