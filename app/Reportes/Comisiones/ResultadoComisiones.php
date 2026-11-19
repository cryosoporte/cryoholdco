<?php

namespace App\Reportes\Comisiones;

/**
 * Todas las comisiones de un período, con lo que no se pudo asignar.
 */
final readonly class ResultadoComisiones
{
    /**
     * @param  list<ComisionCalculada>  $comisiones  una por colaborador y meta, en el orden de las metas
     * @param  array<string, int>  $nombresSinConfigurar  nombre de NetSuite => contratos que no se asignaron porque nadie tiene ese CODIGO_NETSUITE
     * @param  array<string, int>  $serviciosSinLinea  servicio => filas sin línea en COM_SERVICIO_LINEA
     * @param  int  $filasBase  filas de la fuente que cuentan para comisiones en el período
     */
    public function __construct(
        public PeriodoComision $periodo,
        public array $comisiones,
        public array $nombresSinConfigurar,
        public array $serviciosSinLinea,
        public int $filasBase = 0,
    ) {}

    public function total(): float
    {
        return round(array_sum(array_map(
            fn (ComisionCalculada $comision): float => $comision->adelanto === null ? $comision->comision() : $comision->pendiente(),
            $this->comisiones,
        )), 2);
    }

    /**
     * @return list<ComisionCalculada>
     */
    public function delAsesor(int $asesorId): array
    {
        return array_values(array_filter(
            $this->comisiones,
            fn (ComisionCalculada $comision): bool => $comision->asesor->id === $asesorId,
        ));
    }
}
