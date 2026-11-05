<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SerologiaController extends Controller
{
    public function index(Request $request): Response
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:pendiente,tomada,recibido'],
            'asesor' => ['nullable', 'string', 'max:100'],
        ]);

        $buscar = trim($datos['buscar'] ?? '');
        $estado = $datos['estado'] ?? null;
        $asesor = $datos['asesor'] ?? null;

        $consulta = DB::table('VEN_CONTRATOS as C1')
            ->join('VEN_CLIENTES as C2', function ($join) {
                $join->on('C1.NO_CIA', '=', 'C2.NO_CIA')
                    ->on('C1.IDENTIFICACION1', '=', 'C2.IDENTIFICACION');
            })
            ->leftJoin('LAB_COLECTAS as C3', function ($join) {
                $join->on('C1.NO_CIA', '=', 'C3.NO_CIA')
                    ->on('C1.NUMERO_CONTRATO', '=', 'C3.NUMERO_CONTRATO');
            })
            ->leftJoin('OPE_TOMAS_SEROLOGICAS as C4', function ($join) {
                $join->on('C1.NO_CIA', '=', 'C4.NO_CIA')
                    ->on('C1.NUMERO_BASE', '=', 'C4.NUMERO_CONTRATO_BASE');
            })
            ->leftJoin('USERS as U', function ($join) {
                $join->on('C1.VENDEDOR', '=', 'U.IDENTIFICACION')
                    ->on('C1.NO_CIA', '=', 'U.EMPRESA');
            })
            ->where('C1.NO_CIA', $request->user()->empresa)
            ->whereIn('C1.SERVICIO', ['S', 'T'])
            ->whereRaw(
                "C1.FECHA_CONTRATO >= TO_DATE(?, 'DD/MM/YYYY')",
                ['01/01/2026']
            )
            ->select([
                'C1.NO_CIA',
                'C1.NUMERO_BASE',
                'C2.IDENTIFICACION',
                'C2.NOMBRE',
                'C3.SEROLOGIA',
                'C4.FECHA_TOMA',
                'C4.FECHA_RESULTADO',
                'U.NOMBRES as asesor_nombre',
            ])
            ->selectRaw('CAST(C1.VENDEDOR AS VARCHAR2(100)) AS asesor_dni')
            ->distinct();

        $asesores = DB::query()
            ->fromSub($consulta, 'opciones_asesores')
            ->select(['asesor_dni as dni', 'asesor_nombre as nombre'])
            ->whereNotNull('asesor_dni')
            ->distinct()
            ->orderBy('nombre')
            ->orderBy('dni')
            ->get();

        $base = DB::query()
            ->fromSub($consulta, 'resultados')
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

        $conteos = [
            'pendiente' => (clone $base)
                ->whereNull('serologia')
                ->whereNull('fecha_toma')
                ->count(),
            'tomada' => (clone $base)
                ->whereNull('serologia')
                ->whereNotNull('fecha_toma')
                ->count(),
            'recibido' => (clone $base)
                ->where('serologia', 'S')
                ->count(),
        ];

        $resultados = (clone $base)
            ->when($estado === 'pendiente', function (Builder $query): void {
                $query->whereNull('serologia')
                    ->whereNull('fecha_toma');
            })
            ->when($estado === 'tomada', function (Builder $query): void {
                $query->whereNull('serologia')
                    ->whereNotNull('fecha_toma');
            })
            ->when($estado === 'recibido', function (Builder $query): void {
                $query->where('serologia', 'S');
            })
            ->orderBy('nombre')
            ->orderBy('numero_base')
            ->orderBy('identificacion')
            ->orderBy('serologia')
            ->orderBy('fecha_toma')
            ->orderBy('fecha_resultado')
            ->orderBy('asesor_dni')
            ->orderBy('asesor_nombre')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('serologia', [
            'resultados' => $resultados,
            'buscar' => $buscar,
            'estado' => $estado,
            'conteos' => $conteos,
            'asesor' => $asesor,
            'asesores' => $asesores,
        ]);
    }
}
