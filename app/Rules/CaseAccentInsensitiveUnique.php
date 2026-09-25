<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Unicidad de nombre insensible a mayúsculas y tildes — "Inducción",
 * "induccion" e "INDUCCIÓN" cuentan como el mismo nombre. Rule::unique()
 * de Laravel compara bytes exactos en PostgreSQL, así que esas 3 variantes
 * pasarían hoy como registros distintos.
 *
 * Compara en PHP vía Str::ascii() (transliteración a ASCII ya incluida en
 * Laravel, sin dependencias nuevas) en vez de depender de la extensión
 * unaccent de PostgreSQL, para no tener que instalarla ni en Neon
 * (producción) ni en el Postgres local. Estos catálogos son pequeños
 * (decenas de filas), así que comparar en PHP contra todas las filas del
 * alcance es un costo trivial. API fluida (where()/ignore()) a propósito
 * parecida a Rule::unique()->where()->ignore(), para ser un reemplazo casi
 * directo en los formularios existentes.
 */
class CaseAccentInsensitiveUnique implements ValidationRule
{
    protected int|string|null $ignoreId = null;

    protected array $scope = [];

    public function __construct(
        protected string $table,
        protected string $column = 'name',
    ) {}

    public function ignore(mixed $id): static
    {
        $this->ignoreId = $id instanceof Model ? $id->getKey() : $id;

        return $this;
    }

    public function where(string $column, mixed $value): static
    {
        $this->scope[$column] = $value;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table($this->table)->select(['id', $this->column]);

        foreach ($this->scope as $column => $scopeValue) {
            $scopeValue === null
                ? $query->whereNull($column)
                : $query->where($column, $scopeValue);
        }

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        $normalized = $this->normalize((string) $value);

        $duplicate = $query->get()->contains(
            fn ($row) => $this->normalize($row->{$this->column}) === $normalized
        );

        if ($duplicate) {
            $fail('El valor de :attribute ya ha sido registrado.');
        }
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
