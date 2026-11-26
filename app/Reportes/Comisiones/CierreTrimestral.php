<?php

namespace App\Reportes\Comisiones;

use App\Models\LiquidacionComision;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreTrimestral
{
    public function __construct(private LiquidacionesComisiones $liquidaciones, private MetasComisiones $metas) {}

    /** @return list<string> */
    public function mesesPendientes(PeriodoComision $trimestre): array
    {
        $aprobados = $this->liquidaciones->mesesAprobados($trimestre);

        return array_values(array_diff($trimestre->meses(), array_keys($aprobados)));
    }

    public function calcular(PeriodoComision $trimestre): ResultadoComisiones
    {
        $aprobados = $this->liquidaciones->mesesAprobados($trimestre);
        $pendientes = array_diff($trimestre->meses(), array_keys($aprobados));
        if ($pendientes !== []) {
            throw ValidationException::withMessages([
                'accion' => 'Falta aprobar las liquidaciones de estos meses: '.implode(', ', $pendientes).'.',
            ]);
        }

        $metas = $this->metas->vigentes($trimestre);
        $tabla = (new LiquidacionComision)->getTable();
        $contratos = $this->contratosPorCargo($tabla, array_values($aprobados), array_unique(array_column($metas, 'linea')));
        $asesores = [];
        $adelantos = [];
        $filas = DB::table($tabla.'_detalle')->join($tabla.' as l', 'l.id', '=', 'liq_id')
            ->whereIn('liq_id', array_values($aprobados))->whereIn('rol_netsuite', array_unique(array_column($metas, 'rol')))
            ->where('meta_id', '<>', 0)
            ->orderBy('l.periodo')->orderBy('nombre')->orderBy('user_id')
            ->get(['user_id', 'rol_netsuite', 'nombre', 'codigo_netsuite', 'linea', 'comision']);
        foreach ($filas as $fila) {
            $usuario = (int) $fila->user_id;
            $rol = $fila->rol_netsuite;
            $asesores[$rol][$usuario] = new AsesorComision($usuario, $fila->nombre, null, $rol, $fila->codigo_netsuite);
            $adelantos[$rol][$usuario][$fila->linea] = round(($adelantos[$rol][$usuario][$fila->linea] ?? 0) + $fila->comision, 2);
        }

        $comisiones = [];
        $filasBase = 0;
        foreach ($metas as $meta) {
            foreach ($asesores[$meta->rol] ?? [] as $usuario => $asesor) {
                $ventas = $contratos[$meta->rol][$usuario][$meta->linea] ?? [];
                $monto = round(array_sum(array_column($ventas, 'monto')), 2);
                $comisiones[] = new ComisionCalculada(
                    $asesor, $meta->evaluar(count($ventas), $monto),
                    adelanto: $adelantos[$meta->rol][$usuario][$meta->linea] ?? 0.0,
                    contratos: $ventas,
                );
                $filasBase += array_sum(array_column($ventas, 'filas'));
            }
        }

        return new ResultadoComisiones($trimestre, $comisiones, [], [], $filasBase, liquidacionesMensuales: $aprobados);
    }

    /**
     * La foto mensual tiene una meta vigente por cargo y línea. Enlazar por
     * liquidación, usuario y línea conserva el cargo con que se aprobó la venta.
     *
     * @param  list<int>  $idsMensuales
     * @param  array<int, string>  $lineas
     * @return array<string, array<int, array<string, list<ContratoComisionable>>>>
     */
    private function contratosPorCargo(string $tabla, array $idsMensuales, array $lineas): array
    {
        $filas = DB::table($tabla.'_contrato as c')
            ->join($tabla.'_detalle as d', function (JoinClause $join): void {
                $join->on('d.liq_id', '=', 'c.liq_id')->on('d.user_id', '=', 'c.user_id')->on('d.linea', '=', 'c.linea');
            })
            ->whereIn('c.liq_id', $idsMensuales)->whereIn('c.linea', $lineas)->where('d.meta_id', '<>', 0)
            ->groupBy('d.rol_netsuite', 'c.user_id', 'c.linea', 'c.numero_contrato')
            ->orderBy('c.numero_contrato')
            ->select('d.rol_netsuite', 'c.user_id', 'c.linea', 'c.numero_contrato')
            ->selectRaw('MIN(c.fecha_emision) as fecha_emision, SUM(c.monto) as monto, SUM(c.filas) as filas')
            ->get();
        $contratos = [];
        foreach ($filas as $fila) {
            $contratos[$fila->rol_netsuite][$fila->user_id][$fila->linea][] = new ContratoComisionable(
                linea: $fila->linea, contrato: $fila->numero_contrato, fechaEmision: substr($fila->fecha_emision, 0, 10),
                monto: (float) $fila->monto, representante: null, captador: null, dueno: null, filas: (int) $fila->filas,
            );
        }

        return $contratos;
    }
}
