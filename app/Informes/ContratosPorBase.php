<?php

namespace App\Informes;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * La consulta base de Informes: un registro por contrato base de la empresa con sus servicios, su familia,
 * su envío y su colecta. La comparten el listado y el resumen.
 */
class ContratosPorBase
{
    public function __construct(private readonly ContratosInformes $contratos) {}

    /**
     * El envío de cada contrato base de la empresa, mirando todos sus contratos (cualquier servicio y estado):
     * si laboratorio envió los documentos de un servicio, el contrato base cuenta como enviado.
     */
    private function enviosPorBase(string $empresa, ?string $numeroBase = null): Builder
    {
        return DB::table('VEN_CONTRATOS as V')
            ->join('LAB_COLECTAS as L', function (JoinClause $join): void {
                $join->on('V.NO_CIA', '=', 'L.NO_CIA')
                    ->on('V.NUMERO_CONTRATO', '=', 'L.NUMERO_CONTRATO');
            })
            ->where('V.NO_CIA', $empresa)
            ->whereNotNull('V.NUMERO_BASE')
            ->when($numeroBase !== null, fn (Builder $query): Builder => $query->where('V.NUMERO_BASE', $numeroBase))
            ->groupBy('V.NUMERO_BASE')
            ->select([
                'V.NUMERO_BASE',
                DB::raw('MAX(COALESCE(L.FLAG_ENVIO_CORREO, 0)) as enviado'),
                DB::raw('MAX(L.ENVIO_FECHA_CORREO) as fecha_envio'),
                DB::raw('MAX(L.USUARIO_ENVIO) as usuario_envio'),
            ]);
    }

    /**
     * Un registro por contrato base: sus contratos de Sangre y Tejido se reúnen en una sola fila. Los contratos base ya
     * vienen filtrados por ContratosInformes (al menos un servicio en LAB_COLECTAS_INFO); C5 indica qué servicio
     * concreto está registrado.
     */
    public function consulta(string $empresa, ?string $numeroBase = null): Builder
    {
        $registrados = DB::table('LAB_COLECTAS_INFO')
            ->select(['NO_CIA', 'NUMERO_CONTRATO'])
            ->distinct();

        return $this->contratos->consulta($empresa)
            ->leftJoin('VEN_CLIENTES as C4', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C4.NO_CIA')
                    ->on('C1.IDENTIFICACION2', '=', 'C4.IDENTIFICACION');
            })
            ->leftJoinSub($registrados, 'C5', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C5.NO_CIA')
                    ->on('C1.NUMERO_CONTRATO', '=', 'C5.NUMERO_CONTRATO');
            })
            ->leftJoinSub($this->enviosPorBase($empresa, $numeroBase), 'C7', function (JoinClause $join): void {
                $join->on('C1.NUMERO_BASE', '=', 'C7.NUMERO_BASE');
            })
            ->whereNotNull('C1.NUMERO_BASE')
            ->when($numeroBase !== null, fn (Builder $query): Builder => $query->where('C1.NUMERO_BASE', $numeroBase))
            ->groupBy('C1.NUMERO_BASE')
            ->select([
                'C1.NUMERO_BASE as numero_base',
                DB::raw('MAX(C2.IDENTIFICACION) as identificacion'),
                DB::raw('MAX(C2.NOMBRE) as nombre'),
                DB::raw('MAX(C2.MAIL_CONTACTO) as mail_contacto'),
                DB::raw('MAX(C2.APATERNO) as apellido_titular1'),
                DB::raw('MAX(C4.APATERNO) as apellido_titular2'),
                DB::raw('MAX(C3.FECHA_COLECTA) as fecha_colecta'),
                DB::raw('MAX(COALESCE(C7.ENVIADO, 0)) as enviado'),
                DB::raw('MAX(C7.FECHA_ENVIO) as fecha_envio'),
                DB::raw('MAX(C7.USUARIO_ENVIO) as usuario_envio'),
                DB::raw("MAX(CASE WHEN C1.SERVICIO = 'S' THEN 1 ELSE 0 END) as tiene_sangre"),
                DB::raw("MAX(CASE WHEN C1.SERVICIO = 'S' THEN C1.LIBERACION END) as liberacion_sangre"),
                DB::raw("MAX(CASE WHEN C1.SERVICIO = 'S' AND C5.NUMERO_CONTRATO IS NOT NULL THEN 1 ELSE 0 END) as sangre_en_info"),
                DB::raw("MAX(CASE WHEN C1.SERVICIO = 'T' THEN 1 ELSE 0 END) as tiene_tejido"),
                DB::raw("MAX(CASE WHEN C1.SERVICIO = 'T' THEN C1.LIBERACION END) as liberacion_tejido"),
                DB::raw("MAX(CASE WHEN C1.SERVICIO = 'T' AND C5.NUMERO_CONTRATO IS NOT NULL THEN 1 ELSE 0 END) as tejido_en_info"),
            ]);
    }
}
