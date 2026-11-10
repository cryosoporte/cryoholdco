<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Sede;
use App\Models\Toma;
use Illuminate\Http\Request;


class LaboratorioController extends Controller
{
    /** Por defecto abre en Suiza Lab, que es quien más toma. */
    public const LABORATORIO_POR_DEFECTO = '20330025213';

    public function index()
    {
        return view('serologia.laboratorio.index', $this->contexto());
    }

    public function consultar(Request $request)
    {
        $datos = $request->validate(
            ['codigo' => ['required', 'string', 'max:40']],
            ['codigo.required' => 'Escanea el QR o escribe el código o el DNI.']
        );

        $contrato = Contrato::buscarPorContratoDNI($datos['codigo']);

        $resultado = match (true) {
            ! $contrato => ['error' => 'No es clienta de Criocord, o el código está mal escrito. Prueba con el DNI.'],
            ! $contrato->tomaActiva() => ['error' => 'Este contrato todavía no está cargado en el sistema de tomas. No tomes la muestra: avisa a Criocord.'],
            default => ['contrato' => $contrato, 'toma' => $contrato->tomaActiva()],
        };

        return $this->responder($request, $resultado);
    }

    public function marcar(Request $request)
    {
        $datos = $request->validate(
            ['codigo' => ['required', 'string', 'max:40']],
            ['codigo.required' => 'Falta el código del contrato.']
        );

        [$laboratorio, $sede] = $this->laboratorioYSede();

        $contrato = Contrato::buscarPorContratoDNI($datos['codigo']);
        $toma = $contrato?->tomaActiva();

        if (! $toma) {
            return $this->responder($request, [
                'error' => 'No encontramos esa toma. Vuelve a escanear.',
            ]);
        }

        // marcarTomada() devuelve false si otra persona se adelantó; en ese
        // caso no es un error, solo hay que mostrar el estado de verdad.
        $recienMarcada = $toma->marcarTomada($laboratorio, $sede);

        return $this->responder($request, [
            'contrato' => $contrato,
            'toma' => $toma,
            'recienMarcada' => $recienMarcada,
        ]);
    }

    public function sede(Request $request)
    {
        $datos = $request->validate([
            'laboratorio' => ['required', 'string', 'max:15'],
            'sede' => ['nullable', 'string', 'max:2'],
        ]);

        $sedes = Sede::deLaboratorio($datos['laboratorio']);

        if ($sedes->isEmpty()) {
            return back()->withErrors(['laboratorio' => 'Ese laboratorio no tiene sedes activas.']);
        }

        // Al cambiar de laboratorio la sede anterior ya no vale: se toma la
        // primera de la lista nueva.
        $sede = $sedes->firstWhere('codigo_sede', $datos['sede'])
            ? $datos['sede']
            : $sedes->first()->codigo_sede;

        session(['laboratorio.ruc' => $datos['laboratorio'], 'laboratorio.sede' => $sede]);

        return $request->expectsJson()
            ? response()->json($this->fragmentos([]))
            : redirect()->route('laboratorio.index');
    }
 
    private function responder(Request $request, array $resultado)
    {
        if ($request->expectsJson()) {
            return response()->json($this->fragmentos($resultado));
        }

        return view('serologia.laboratorio.index', $this->contexto($resultado));
    }

    private function fragmentos(array $resultado): array
    {
        $contexto = $this->contexto($resultado);

        return [
            'semaforo' => view('serologia.laboratorio.partials.semaforo', $contexto)->render(),
            'hoy' => view('serologia.laboratorio.partials.hoy', $contexto)->render(),
            'opciones' => view('serologia.laboratorio.partials.opciones-sede', $contexto)->render(),
        ];
    }

    /** Todo lo que la vista necesita saber, en un solo sitio. */
    private function contexto(array $resultado = []): array
    {
        [$laboratorio, $sede] = $this->laboratorioYSede();

        return array_merge([
            'laboratorios' => Sede::laboratorios(),
            'sedes' => Sede::deLaboratorio($laboratorio),
            'laboratorio' => $laboratorio,
            'sede' => $sede,
            'nombreSede' => Sede::nombre($laboratorio, $sede),
            'hoy' => Toma::tomadasHoy($laboratorio, $sede),
            'contrato' => null,
            'toma' => null,
            'error' => null,
            'recienMarcada' => false,
        ], $resultado);
    }

    /** Laboratorio y sede de la sesión, con el primero disponible por defecto. */
    private function laboratorioYSede(): array
    {
        $laboratorio = session('laboratorio.ruc', self::LABORATORIO_POR_DEFECTO);
        $sedes = Sede::deLaboratorio($laboratorio);

        if ($sedes->isEmpty()) {
            $laboratorio = Sede::laboratorios()->first()?->identificacion;
            $sedes = $laboratorio ? Sede::deLaboratorio($laboratorio) : collect();
        }

        $sede = session('laboratorio.sede');

        if (! $sedes->firstWhere('codigo_sede', $sede)) {
            $sede = $sedes->first()?->codigo_sede;
        }

        return [$laboratorio, $sede];
    }
}
