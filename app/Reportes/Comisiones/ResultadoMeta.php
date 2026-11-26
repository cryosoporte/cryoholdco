<?php

namespace App\Reportes\Comisiones;

/**
 * Lo que logró un asesor frente a una meta y la comisión que le corresponde.
 */
final readonly class ResultadoMeta
{
    public function __construct(
        public MetaComision $meta,
        public float $cantidad,
        public float $monto,
        public ?TramoMeta $tramo,
        public float $comision,
    ) {}

    public function alcanzoMinimo(): bool
    {
        return $this->tramo !== null;
    }

    /**
     * Cuánto le faltó para el mínimo (0 si lo alcanzó), en la unidad de la
     * meta: contratos, años o soles.
     */
    public function faltanteParaMinimo(): float
    {
        return max(0.0, round($this->meta->minimo - $this->meta->logrado($this->cantidad, $this->monto), 2));
    }
}
