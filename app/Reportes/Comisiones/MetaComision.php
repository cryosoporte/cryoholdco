<?php

namespace App\Reportes\Comisiones;

/**
 * Una meta de COM_META con sus tramos: cuánto se paga a un rol por una línea
 * de servicio según lo que logre en el período.
 */
final readonly class MetaComision
{
    /**
     * @param  list<TramoMeta>  $tramos  ordenados por número de tramo
     */
    public function __construct(
        public int $id,
        public string $rol,
        public Atribucion $atribucion,
        public string $linea,
        public Periodicidad $periodicidad,
        public TipoMeta $tipo,
        public BaseTramo $baseTramo,
        public float $minimo,
        public array $tramos,
    ) {}

    /**
     * Ubica el tramo con la cantidad o con el monto, según BASE_TRAMO, y
     * calcula la comisión. Debajo del mínimo no hay tramo y la comisión es 0.
     *
     * @param  float  $cantidad  contratos logrados o, en morosos, años de atraso
     * @param  float  $monto  monto base en soles, sin IGV
     */
    public function evaluar(float $cantidad, float $monto): ResultadoMeta
    {
        $logrado = $this->logrado($cantidad, $monto);
        $tramo = $logrado >= $this->minimo ? $this->tramoPara($logrado) : null;

        $comision = match (true) {
            $tramo === null => 0.0,
            $this->tipo === TipoMeta::PorContrato => $cantidad * $tramo->valor,
            $this->tipo === TipoMeta::Porcentaje => $monto * $tramo->valor,
            $this->tipo === TipoMeta::MontoFijo => $tramo->valor,
        };

        return new ResultadoMeta($this, $cantidad, $monto, $tramo, round($comision, 2));
    }

    public function logrado(float $cantidad, float $monto): float
    {
        return $this->baseTramo === BaseTramo::Monto ? $monto : $cantidad;
    }

    public function tramoPara(float $logrado): ?TramoMeta
    {
        foreach ($this->tramos as $tramo) {
            if ($tramo->incluye($logrado)) {
                return $tramo;
            }
        }

        return null;
    }
}
