<?php

namespace App\Serologia;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El listado de clientes en seguimiento de serología de una empresa:
 * filtros, conteos por estado, asesores y la página de resultados.
 */
class ListadoSerologia
{
    /** Estados por los que se puede contar y filtrar, en el orden en que se muestran los conteos. */
    public const ESTADOS = ['pendiente', 'tomada', 'recibido', 'por_vencer'];

    /** Un pendiente está "por vencer" si nació en los últimos días indicados aquí. */
    public const DIAS_POR_VENCER = 7;

    public const POR_PAGINA = 20;

    public function __construct(
        private readonly PlazoSerologia $plazoSerologia,
        private readonly DetalleSerologia $detalleSerologia,
    ) {}

    /**
     * @return array{resultados: LengthAwarePaginator, conteos: array<string, int>, asesores: Collection<int, object>}
     */
    public function consultar(string $empresa, string $buscar, ?string $estado, ?string $asesor, CarbonImmutable $hoy): array
    {
        $contratos = $this->contratosEnSeguimiento($empresa);
        $asesores = $this->asesores($contratos);
        $filtrados = $this->filtrar($contratos, $buscar, $asesor);

        $conteos = [];
        foreach (self::ESTADOS as $opcion) {
            $conteos[$opcion] = $this->aplicarEstado(clone $filtrados, $opcion, $hoy)->count();
        }

        $resultados = (clone $filtrados)
            ->when($estado !== null, fn (Builder $query): Builder => $this->aplicarEstado($query, $estado, $hoy))
            ->orderBy('nombre')
            ->orderBy('numero_base')
            ->orderBy('identificacion')
            ->orderBy('serologia')
            ->orderBy('numero_toma')
            ->orderBy('fecha_toma')
            ->orderBy('fecha_resultado')
            ->orderBy('asesor_dni')
            ->orderBy('asesor_nombre')
            ->orderBy('fecha_nacimiento')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $fichas = $this->detalleSerologia->cargarParaFilas($resultados->getCollection(), $empresa);
        $resultados->through(fn (object $fila): object => $this->completarFila($fila, $fichas, $hoy));

        return [
            'resultados' => $resultados,
            'conteos' => $conteos,
            'asesores' => $asesores,
        ];
    }

    /**
     * Un registro por contrato y toma, con su cliente, su serología y su asesor.
     */
    private function contratosEnSeguimiento(string $empresa): Builder
    {
        return DB::table('VEN_CONTRATOS as C1')
            ->join('VEN_CLIENTES as C2', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C2.NO_CIA')
                    ->on('C1.IDENTIFICACION1', '=', 'C2.IDENTIFICACION');
            })
            ->leftJoin('LAB_COLECTAS as C3', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C3.NO_CIA')
                    ->on('C1.NUMERO_CONTRATO', '=', 'C3.NUMERO_CONTRATO');
            })
            ->leftJoin('OPE_TOMAS_SEROLOGICAS as C4', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C4.NO_CIA')
                    ->on('C1.NUMERO_BASE', '=', 'C4.NUMERO_CONTRATO_BASE');
            })
            ->leftJoin('USERS as U', function (JoinClause $join): void {
                $join->on('C1.VENDEDOR', '=', 'U.IDENTIFICACION')
                    ->on('C1.NO_CIA', '=', 'U.EMPRESA');
            })
            ->where('C1.NO_CIA', $empresa)
            ->whereIn('C1.SERVICIO', AlcanceSerologia::SERVICIOS)
            ->whereRaw(
                "C1.FECHA_CONTRATO >= TO_DATE(?, 'DD/MM/YYYY')",
                [AlcanceSerologia::fechaInicio()->format('d/m/Y')]
            )
            ->select([
                'C1.NO_CIA',
                'C1.NUMERO_BASE',
                'C2.IDENTIFICACION',
                'C2.NOMBRE',
                'C3.SEROLOGIA',
                'C4.NUMERO_TOMA',
                'C4.FECHA_TOMA',
                'C4.FECHA_RESULTADO',
                'U.NOMBRES as asesor_nombre',
            ])
            ->selectRaw('CAST(C1.VENDEDOR AS VARCHAR2(100)) AS asesor_dni')
            ->selectRaw("TO_CHAR(C1.FECHA_NACIMIENTO, 'YYYY-MM-DD') AS fecha_nacimiento")
            ->selectRaw("TO_CHAR(C4.FECHA_TOMA, 'YYYY-MM-DD') AS fecha_toma_dia")
            ->selectRaw("TO_CHAR(C4.FECHA_RESULTADO, 'YYYY-MM-DD') AS fecha_resultado_dia")
            ->distinct();
    }

    /**
     * Los asesores de los contratos en seguimiento, para el filtro de la pantalla.
     *
     * @return Collection<int, object>
     */
    private function asesores(Builder $contratos): Collection
    {
        return DB::query()
            ->fromSub($contratos, 'opciones_asesores')
            ->select(['asesor_dni as dni', 'asesor_nombre as nombre'])
            ->whereNotNull('asesor_dni')
            ->distinct()
            ->orderBy('nombre')
            ->orderBy('dni')
            ->get();
    }

    private function filtrar(Builder $contratos, string $buscar, ?string $asesor): Builder
    {
        return DB::query()
            ->fromSub($contratos, 'resultados')
            ->when($buscar !== '', function (Builder $query) use ($buscar): void {
                $texto = '%'.mb_strtoupper($buscar).'%';

                $query->where(function (Builder $filtro) use ($texto): void {
                    $filtro->whereRaw('UPPER(nombre) LIKE ?', [$texto])
                        ->orWhereRaw('UPPER(CAST(identificacion AS VARCHAR2(100))) LIKE ?', [$texto])
                        ->orWhereRaw('UPPER(CAST(numero_base AS VARCHAR2(100))) LIKE ?', [$texto]);
                });
            })
            ->when($asesor !== null, function (Builder $query) use ($asesor): void {
                $query->where('asesor_dni', $asesor);
            });
    }

    /**
     * La única definición de cada estado: la usan tanto los conteos como el filtro.
     */
    private function aplicarEstado(Builder $query, string $estado, CarbonImmutable $hoy): Builder
    {
        return match ($estado) {
            'pendiente' => $query->whereNull('serologia')
                ->whereNull('fecha_toma'),
            'tomada' => $query->whereNull('serologia')
                ->whereNotNull('fecha_toma'),
            'recibido' => $query->where('serologia', AlcanceSerologia::SEROLOGIA_RECIBIDA),
            'por_vencer' => $query->whereNull('serologia')
                ->whereNull('fecha_toma')
                ->whereNotNull('fecha_nacimiento')
                ->whereBetween('fecha_nacimiento', [
                    $hoy->subDays(self::DIAS_POR_VENCER)->toDateString(),
                    $hoy->toDateString(),
                ]),
        };
    }

    /**
     * Agrega a la fila su ficha y su plazo, y quita las fechas auxiliares de la consulta.
     *
     * @param  Collection<string, object>  $fichas
     */
    private function completarFila(object $fila, Collection $fichas, CarbonImmutable $hoy): object
    {
        $fila->numero_toma = $fila->numero_toma === null ? null : (int) $fila->numero_toma;
        $fila->detalle = $this->detalleSerologia->paraFila($fila, $fichas, $hoy);
        $fila->plazo = $this->plazoSerologia->paraRegistro(
            serologia: $fila->serologia,
            fechaNacimiento: $fila->fecha_nacimiento,
            fechaToma: $fila->fecha_toma_dia,
            fechaResultado: $fila->fecha_resultado_dia,
            hoy: $hoy,
        );
        unset($fila->fecha_toma_dia, $fila->fecha_resultado_dia);

        return $fila;
    }
}
