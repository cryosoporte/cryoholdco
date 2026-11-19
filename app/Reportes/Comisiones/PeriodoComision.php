<?php

namespace App\Reportes\Comisiones;

use Carbon\CarbonImmutable;

/**
 * Rango de fechas de emisión que se liquida: un mes o un trimestre. Las
 * fechas son días completos en hora de Lima, sin hora.
 */
final readonly class PeriodoComision
{
    public function __construct(
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
        public Periodicidad $periodicidad,
    ) {}

    public static function mes(int $anio, int $mes): self
    {
        $desde = CarbonImmutable::create($anio, $mes, 1);

        return new self($desde, $desde->endOfMonth()->startOfDay(), Periodicidad::Mensual);
    }

    public static function trimestre(int $anio, int $trimestre): self
    {
        $desde = CarbonImmutable::create($anio, ($trimestre - 1) * 3 + 1, 1);

        return new self($desde, $desde->addMonths(2)->endOfMonth()->startOfDay(), Periodicidad::Trimestral);
    }

    /**
     * Primer instante después del período, para comparar fechas con hora
     * (fecha_emision < hastaExclusivo).
     */
    public function hastaExclusivo(): CarbonImmutable
    {
        return $this->hasta->addDay();
    }
}
