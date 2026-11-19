<?php

namespace App\Reportes\Comisiones;

/**
 * La comisión de un colaborador por una meta en el período.
 *
 * En una meta trimestral, el adelanto es lo que ya se le pagó por la misma
 * línea en los meses del trimestre y el pendiente (recuperable) es la
 * diferencia a favor del colaborador, si la hay.
 */
final readonly class ComisionCalculada
{
    public function __construct(
        public AsesorComision $asesor,
        public ResultadoMeta $resultado,
        public ?float $adelanto = null,
    ) {}

    public function comision(): float
    {
        return $this->resultado->comision;
    }

    /**
     * Lo que se paga al cierre del trimestre: comisión trimestral menos el
     * adelanto, solo si es positivo. En una meta mensual es 0.
     */
    public function pendiente(): float
    {
        if ($this->adelanto === null) {
            return 0.0;
        }

        return max(0.0, round($this->resultado->comision - $this->adelanto, 2));
    }
}
