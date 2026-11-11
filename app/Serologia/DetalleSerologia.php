<?php

namespace App\Serologia;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La ficha de cada cliente del listado: contacto, FPP, parto real, sede y plazo.
 */
class DetalleSerologia
{
    public function __construct(private readonly PlazoSerologia $plazoSerologia) {}

    /**
     * Carga en una sola consulta la ficha de los clientes de una página del listado.
     *
     * @param  Collection<int, object>  $filas
     * @return Collection<string, object>
     */
    public function cargarParaFilas(Collection $filas, string $empresa): Collection
    {
        $contratos = $filas->pluck('numero_base')->filter()->unique()->values();
        if ($contratos->isEmpty()) {
            return collect();
        }

        return DB::table('VEN_CONTRATOS as C')
            ->join('VEN_CLIENTES as CL', function (JoinClause $join): void {
                $join->on('CL.NO_CIA', '=', 'C.NO_CIA')
                    ->on('CL.IDENTIFICACION', '=', 'C.IDENTIFICACION1');
            })
            ->leftJoin('VEN_PROSPECTOS as P', function (JoinClause $join): void {
                $join->on('P.NO_CIA', '=', 'C.NO_CIA')
                    ->on('P.NUMERO_PROSPECTO', '=', 'C.NUMERO_PROSPECTO');
            })
            ->leftJoin('LAB_COLECTAS as L', function (JoinClause $join): void {
                $join->on('L.NO_CIA', '=', 'C.NO_CIA')
                    ->on('L.NUMERO_CONTRATO', '=', 'C.NUMERO_CONTRATO');
            })
            ->leftJoin('OPE_TOMAS_SEROLOGICAS as T', function (JoinClause $join): void {
                $join->on('T.NO_CIA', '=', 'C.NO_CIA')
                    ->on('T.NUMERO_CONTRATO_BASE', '=', 'C.NUMERO_BASE');
            })
            ->leftJoin('LAB_LABORATORIOS_SEDES as S', function (JoinClause $join): void {
                $join->on('S.NO_CIA', '=', 'T.NO_CIA')
                    ->on('S.IDENTIFICACION', '=', 'T.LABORATORIO_TOMA')
                    ->on('S.CODIGO_SEDE', '=', 'T.SEDE_TOMA');
            })
            ->where('C.NO_CIA', $empresa)
            ->whereIn('C.NUMERO_BASE', $contratos)
            ->whereIn('C.SERVICIO', AlcanceSerologia::SERVICIOS)
            ->where('C.FECHA_CONTRATO', '>=', AlcanceSerologia::fechaInicio())
            ->select([
                'C.NUMERO_BASE',
                'C.IDENTIFICACION1 as identificacion',
                'T.NUMERO_TOMA',
                'CL.CELULAR_CONTACTO as celular',
            ])
            ->selectRaw('MAX(P.FECHA_PARTO) AS fpp_prospecto')
            ->selectRaw('MAX(L.FECHA_NACIMIENTO) AS parto_colecta')
            ->selectRaw('MAX(CASE WHEN C.CONFIRM_NAC = 1 THEN C.FECHA_NACIMIENTO END) AS parto_confirmado')
            ->selectRaw('MAX(C.FECHA_NACIMIENTO) AS fecha_contrato_nacimiento')
            ->selectRaw('MAX(S.DESCRIPCION_SEDE) AS sede_toma')
            ->groupBy('C.NUMERO_BASE', 'C.IDENTIFICACION1', 'T.NUMERO_TOMA', 'CL.CELULAR_CONTACTO')
            ->get()
            ->keyBy($this->clave(...));
    }

    /**
     * La ficha de una fila del listado, a partir de las fichas cargadas para su página.
     *
     * @param  Collection<string, object>  $fichas
     * @return array{celular: ?string, fpp: ?string, parto_real: ?string, sede_toma: ?string, plazo: array{situacion: string, fecha_apertura: ?string, fecha_limite: ?string, dias_restantes: ?int, porcentaje_transcurrido: ?int, texto: string}}
     */
    public function paraFila(object $fila, Collection $fichas, CarbonImmutable $hoy): array
    {
        return $this->formatear($fichas->get($this->clave($fila)), $hoy);
    }

    private function clave(object $fila): string
    {
        return serialize([$fila->numero_base, $fila->identificacion, (int) $fila->numero_toma]);
    }

    /**
     * @return array{celular: ?string, fpp: ?string, parto_real: ?string, sede_toma: ?string, plazo: array{situacion: string, fecha_apertura: ?string, fecha_limite: ?string, dias_restantes: ?int, porcentaje_transcurrido: ?int, texto: string}}
     */
    private function formatear(?object $cliente, CarbonImmutable $hoy): array
    {
        $partoReal = $cliente?->parto_colecta ?? $cliente?->parto_confirmado;
        $fpp = $cliente?->fpp_prospecto ?? ($partoReal === null ? $cliente?->fecha_contrato_nacimiento : null);
        $partoReal = $partoReal === null ? null : substr($partoReal, 0, 10);
        $fpp = $fpp === null ? null : substr($fpp, 0, 10);

        return [
            'celular' => trim($cliente?->celular ?? '') ?: null,
            'fpp' => $fpp,
            'parto_real' => $partoReal,
            'sede_toma' => trim($cliente?->sede_toma ?? '') ?: null,
            'plazo' => $this->plazoSerologia->calcular(
                $partoReal ?? $fpp,
                $hoy,
                diasAntes: 10,
                diasDespues: 30,
            ),
        ];
    }
}
