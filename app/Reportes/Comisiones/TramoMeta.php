<?php

namespace App\Reportes\Comisiones;

/**
 * Un tramo de COM_META_TRAMO: desde, hasta (null = sin tope) y valor.
 */
final readonly class TramoMeta
{
    public function __construct(
        public int $numero,
        public float $desde,
        public ?float $hasta,
        public float $valor,
    ) {}

    public function incluye(float $logrado): bool
    {
        return $logrado >= $this->desde && ($this->hasta === null || $logrado <= $this->hasta);
    }
}
