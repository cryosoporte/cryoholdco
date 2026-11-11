<?php

namespace App\Http\Controllers\Serologia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Serologia\EnviarRecordatorioRequest;
use App\Http\Requests\Serologia\FiltrarSerologiaRequest;
use App\Serologia\AlcanceSerologia;
use App\Serologia\EnviarRecordatorioSerologia;
use App\Serologia\ListadoSerologia;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SerologiaController extends Controller
{
    public function index(FiltrarSerologiaRequest $request, ListadoSerologia $listado): Response
    {
        $datos = $request->validated();

        $buscar = trim($datos['buscar'] ?? '');
        $estado = $datos['estado'] ?? null;
        $asesor = $datos['asesor'] ?? null;
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);

        $pagina = $listado->consultar(
            empresa: $empresa,
            buscar: $buscar,
            estado: $estado,
            asesor: $asesor,
            hoy: CarbonImmutable::today(AlcanceSerologia::ZONA_HORARIA),
        );

        return Inertia::render('serologia', [
            'resultados' => $pagina['resultados'],
            'buscar' => $buscar,
            'estado' => $estado,
            'conteos' => $pagina['conteos'],
            'asesor' => $asesor,
            'asesores' => $pagina['asesores'],
        ]);
    }

    public function enviarRecordatorio(EnviarRecordatorioRequest $request, EnviarRecordatorioSerologia $recordatorio): JsonResponse
    {
        $datos = $request->validated();

        abort_if(blank($request->user()->empresa), 403);

        $enviado = $recordatorio->enviar(
            empresa: $request->user()->empresa,
            numeroBase: $datos['numero_base'],
            identificacion: $datos['identificacion'],
        );

        if (! $enviado) {
            return response()->json([
                'message' => 'No se pudo enviar el recordatorio. Intenta de nuevo más tarde.',
            ], 502);
        }

        return response()->json(['message' => 'Recordatorio enviado correctamente.']);
    }
}
