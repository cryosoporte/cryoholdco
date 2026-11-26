<?php

namespace App\Reportes\Comisiones;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lee de COM_META y COM_META_TRAMO las metas activas que rigen un período.
 * Solo consulta: las metas las administra el área de comisiones.
 */
class MetasComisiones
{
    /**
     * Metas activas de la periodicidad del período cuya vigencia lo cubre
     * completo. Si dos metas del mismo rol y línea lo cubren, gana la de
     * vigencia más reciente.
     *
     * @return list<MetaComision>
     */
    public function vigentes(PeriodoComision $periodo): array
    {
        $metas = DB::table('com_meta')
            ->where('estado', 'A')
            ->where('periodicidad', $periodo->periodicidad->value)
            ->where('vig_desde', '<=', $periodo->desde->toDateTimeString())
            ->where(function (Builder $consulta) use ($periodo): void {
                $consulta->whereNull('vig_hasta')->orWhere('vig_hasta', '>=', $periodo->hasta->toDateTimeString());
            })
            ->orderBy('vig_desde')
            ->get()
            ->keyBy(fn (object $meta): string => $meta->rol_cod.'|'.$meta->linea_cod)
            ->sortBy(fn (object $meta): int => (int) $meta->meta_id)
            ->values();

        $tramos = DB::table('com_meta_tramo')
            ->whereIn('meta_id', $metas->pluck('meta_id')->all())
            ->orderBy('nro_tramo')
            ->get()
            ->groupBy(fn (object $tramo): int => (int) $tramo->meta_id);

        return $metas->map(fn (object $meta): MetaComision => new MetaComision(
            id: (int) $meta->meta_id,
            rol: $meta->rol_cod,
            atribucion: Atribucion::from($meta->atribucion),
            linea: $meta->linea_cod,
            periodicidad: Periodicidad::from($meta->periodicidad),
            tipo: TipoMeta::from($meta->tipo_cod),
            baseTramo: BaseTramo::from($meta->base_tramo),
            minimo: (float) $meta->minimo,
            tramos: $tramos->get((int) $meta->meta_id, collect())->map(fn (object $tramo): TramoMeta => new TramoMeta(
                numero: (int) $tramo->nro_tramo,
                desde: (float) $tramo->desde,
                hasta: $tramo->hasta === null ? null : (float) $tramo->hasta,
                valor: (float) $tramo->valor,
            ))->values()->all(),
        ))->all();
    }
}
