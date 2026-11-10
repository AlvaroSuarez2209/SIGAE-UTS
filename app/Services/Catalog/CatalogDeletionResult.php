<?php

namespace App\Services\Catalog;

/**
 * Resultado de un intento de borrado real de catálogo — "tiene
 * dependencias" es un desenlace normal y esperado (no una excepción:
 * mismo criterio que blocksDeactivation() en cada Index, que ya
 * devuelve una razón en vez de lanzar), así que CatalogDeletionService
 * devuelve esto en vez de lanzar una excepción para ese caso. Solo un
 * QueryException que no sea una violación de llave foránea sigue
 * propagándose como excepción real (un bug, no un desenlace esperado).
 */
final class CatalogDeletionResult
{
    private function __construct(
        public readonly bool $deleted,
        public readonly ?string $message = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function blocked(string $message): self
    {
        return new self(false, $message);
    }
}
