<?php

namespace App\Reportes\Comisiones;

/**
 * La comisión de un colaborador por una meta en el período.
 *
 * En una meta trimestral, el adelanto es lo que ya se le pagó por la misma
 * línea en los meses del trimestre y el pendiente (recuperable) es la
 * diferencia a favor del colaborador, si la hay.
 *
 * En una línea por antigüedad (morosos), las partes son los bloques de 3, 4
 * y 5+ años, cada uno con su monto y su porcentaje; la comisión es su suma.
 */
final readonly class ComisionCalculada
{
    /**
     * @param  list<ResultadoMeta>  $partes  bloques por tramo, solo en líneas por antigüedad
     */
    public function __construct(
        public AsesorComision $asesor,
        public ResultadoMeta $resultado,
        public ?float $adelanto = null,
        public array $partes = [],
    ) {}

    public function comision(): float
    {
        return $this->resultado->comision;
    }

    public function alcanzo(): bool
    {
        return $this->partes === []
            ? $this->resultado->alcanzoMinimo()
            : $this->resultado->comision > 0;
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
