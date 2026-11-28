<?php

namespace App\Http\Controllers\Informes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informes\FiltrarInformesRequest;
use App\Informes\AlcanceInformes;
use App\Informes\FiltrosInformes;
use App\Informes\ListadoInformes;
use App\Informes\ResumenInformes;
use Inertia\Inertia;
use Inertia\Response;

class InformesController extends Controller
{
    public function index(FiltrarInformesRequest $request, ListadoInformes $listado, ResumenInformes $resumen): Response
    {
        $filtros = FiltrosInformes::desde($request->validated());
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);

        $pagina = $listado->consultar(empresa: $empresa, filtros: $filtros);

        return Inertia::render('informes', [
            'resultados' => $pagina['resultados'],
            'resumen' => $resumen->calcular($empresa, $filtros),
            'buscar' => $filtros->buscar,
            'envio' => $filtros->envio,
            'antiguedad' => $filtros->antiguedad,
            'sangre' => $filtros->sangre,
            'tejido' => $filtros->tejido,
            'liberacion' => $filtros->liberacion,
            'envios' => AlcanceInformes::ENVIOS,
            'unidad_disponible' => $pagina['unidad_disponible'],
            'maximo_envio_masivo' => AlcanceInformes::MAXIMO_POR_ENVIO_MASIVO,
        ]);
    }
}
