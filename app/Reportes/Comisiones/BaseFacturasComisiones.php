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
 *
 * montosNetos() es la excepción: suma todos los comprobantes emitidos.
 */
class BaseFacturasComisiones
{
    public const TIPO_NOTA_DE_CREDITO = '07- Nota de crédito';

    /** Servicios de anualidad que se evalúan como morosos; cada uno por separado. */
    public const SERVICIOS_ANUALIDAD = ['ANUALIDAD SANGRE', 'ANUALIDAD TEJIDO'];

    /** Mínimo de anualidades pagadas para que cuente como morosa. */
    public const MINIMO_ANUALIDADES_MOROSAS = 3;

    /** La anualidad más antigua debe empezar al menos estos años antes del año actual. */
    public const ANTIGUEDAD_MINIMA_MOROSA = 2;

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
     * Suma de SUBTOTAL_SOLES de todos los comprobantes del período en cada
     * línea pedida, sin excluir notas de crédito ni comprobantes con
     * "Aplicación de transacción". Las líneas sin comprobantes no aparecen.
     *
     * @param  list<string>  $lineas
     * @return array<string, float> línea => monto neto
     */
    public function montosNetos(PeriodoComision $periodo, array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        return $this->emitidasEnElPeriodo($periodo)
            ->join('com_servicio_linea as l', 'l.servicio', '=', 'f.servicio')
            ->whereIn('l.linea_cod', $lineas)
            ->groupBy('l.linea_cod')
            ->selectRaw('l.linea_cod as linea, SUM(f.subtotal_soles) as monto')
            ->get()
            ->mapWithKeys(fn (object $fila): array => [$fila->linea => round((float) $fila->monto, 2)])
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

    /**
     * Anualidades cobradas en el período que cuentan como morosas, agrupadas
     * por contrato y servicio: Sangre y Tejido del mismo contrato son dos.
     *
     * Cada anualidad pagada es un año. Cuenta si se pagaron al menos 3 y la
     * más antigua (por INICIO_PERIODO) empieza en $anioActual - 2 o antes. Las
     * anualidades sin fechas en la nota no se evalúan.
     *
     * @return list<AnualidadMorosa> ordenadas por contrato y servicio
     */
    public function anualidadesMorosas(PeriodoComision $periodo, int $anioActual): array
    {
        $anioLimite = $anioActual - self::ANTIGUEDAD_MINIMA_MOROSA;

        return $this->anualidadesDelPeriodo($periodo)
            ->whereNotNull('f.numero_contrato_oracle')
            ->whereNotNull('f.inicio_periodo')
            ->groupBy('f.numero_contrato_oracle', 'f.servicio')
            ->havingRaw('COUNT(*) >= ?', [self::MINIMO_ANUALIDADES_MOROSAS])
            ->orderBy('f.numero_contrato_oracle')
            ->orderBy('f.servicio')
            ->selectRaw('f.numero_contrato_oracle as contrato, f.servicio as servicio, MAX(f.dueno) as dueno,'
                .' COUNT(*) as anios, MIN(f.inicio_periodo) as inicio, SUM(f.importe) as monto')
            ->get()
            ->map(fn (object $fila): AnualidadMorosa => new AnualidadMorosa(
                contrato: $fila->contrato,
                servicio: $fila->servicio,
                dueno: $fila->dueno,
                anios: (int) $fila->anios,
                anioInicioMasAntiguo: (int) substr((string) $fila->inicio, 0, 4),
                monto: round((float) $fila->monto, 2),
            ))
            ->filter(fn (AnualidadMorosa $anualidad): bool => $anualidad->anioInicioMasAntiguo <= $anioLimite)
            ->values()
            ->all();
    }

    /**
     * Anualidades cobradas en el período cuya nota no trae las fechas del
     * período que cubren: no se pueden evaluar como morosas.
     */
    public function anualidadesSinPeriodo(PeriodoComision $periodo): int
    {
        return $this->anualidadesDelPeriodo($periodo)->whereNull('f.inicio_periodo')->count();
    }

    private function anualidadesDelPeriodo(PeriodoComision $periodo): Builder
    {
        return $this->facturasDelPeriodo($periodo)->whereIn('f.servicio', self::SERVICIOS_ANUALIDAD);
    }

    private function facturasDelPeriodo(PeriodoComision $periodo): Builder
    {
        return $this->emitidasEnElPeriodo($periodo)
            ->whereNull('f.aplicacion_transaccion')
            ->where(function (Builder $consulta): void {
                $consulta->whereNull('f.tipo_documento')->orWhere('f.tipo_documento', '<>', self::TIPO_NOTA_DE_CREDITO);
            });
    }

    private function emitidasEnElPeriodo(PeriodoComision $periodo): Builder
    {
        return DB::table($this->fuente->tabla().' as f')
            ->where('f.fecha_emision', '>=', $periodo->desde->toDateTimeString())
            ->where('f.fecha_emision', '<', $periodo->hastaExclusivo()->toDateTimeString());
    }
}
