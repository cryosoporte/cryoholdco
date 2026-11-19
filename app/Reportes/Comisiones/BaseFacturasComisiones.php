<?php

namespace App\Reportes\Comisiones;

use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Facturas de la fuente que cuentan para comisiones en un período. Solo
 * consulta.
 *
 * Una factura cuenta si:
 * - su fecha de emisión cae en el período;
 * - no es nota de crédito;
 * - no tiene "Aplicación de transacción";
 * - su servicio tiene línea en COM_SERVICIO_LINEA.
 */
class BaseFacturasComisiones
{
    public const TIPO_NOTA_DE_CREDITO = '07- Nota de crédito';

    public function __construct(private FuenteFacturasOracle $fuente) {}

    /**
     * Un elemento por contrato y línea, ordenados por línea y contrato. Las
     * filas sin número de contrato no se cuentan.
     *
     * @return list<ContratoComisionable>
     */
    public function contratos(PeriodoComision $periodo): array
    {
        return $this->facturasDelPeriodo($periodo)
            ->join('com_servicio_linea as l', 'l.servicio', '=', 'f.servicio')
            ->whereNotNull('f.numero_contrato_oracle')
            ->groupBy('l.linea_cod', 'f.numero_contrato_oracle')
            ->orderBy('l.linea_cod')
            ->orderBy('f.numero_contrato_oracle')
            ->selectRaw('l.linea_cod as linea, f.numero_contrato_oracle as contrato, MIN(f.fecha_emision) as fecha_emision,'
                .' SUM(f.subtotal_soles) as monto, MAX(f.representante_ventas) as representante,'
                .' MAX(f.captador_final) as captador, MAX(f.dueno) as dueno, COUNT(*) as filas')
            ->get()
            ->map(fn (object $fila): ContratoComisionable => new ContratoComisionable(
                linea: $fila->linea,
                contrato: $fila->contrato,
                fechaEmision: substr((string) $fila->fecha_emision, 0, 10),
                monto: round((float) $fila->monto, 2),
                representante: $fila->representante,
                captador: $fila->captador,
                dueno: $fila->dueno,
                filas: (int) $fila->filas,
            ))
            ->all();
    }

    /**
     * Servicios del período que no tienen línea configurada, con cuántas
     * filas tiene cada uno. Sirve para avisar qué falta agregar a
     * COM_SERVICIO_LINEA.
     *
     * @return array<string, int> servicio => filas, de más a menos filas
     */
    public function serviciosSinLinea(PeriodoComision $periodo): array
    {
        return $this->facturasDelPeriodo($periodo)
            ->leftJoin('com_servicio_linea as l', 'l.servicio', '=', 'f.servicio')
            ->whereNull('l.linea_cod')
            ->whereNotNull('f.servicio')
            ->groupBy('f.servicio')
            ->orderByRaw('COUNT(*) desc')
            ->orderBy('f.servicio')
            ->selectRaw('f.servicio as servicio, COUNT(*) as filas')
            ->get()
            ->mapWithKeys(fn (object $fila): array => [$fila->servicio => (int) $fila->filas])
            ->all();
    }

    private function facturasDelPeriodo(PeriodoComision $periodo): Builder
    {
        return DB::table($this->fuente->tabla().' as f')
            ->where('f.fecha_emision', '>=', $periodo->desde->toDateTimeString())
            ->where('f.fecha_emision', '<', $periodo->hastaExclusivo()->toDateTimeString())
            ->whereNull('f.aplicacion_transaccion')
            ->where(function (Builder $consulta): void {
                $consulta->whereNull('f.tipo_documento')->orWhere('f.tipo_documento', '<>', self::TIPO_NOTA_DE_CREDITO);
            });
    }
}
