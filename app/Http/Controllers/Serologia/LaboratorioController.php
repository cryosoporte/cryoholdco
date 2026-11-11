<?php

namespace App\Http\Controllers\Serologia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Serologia\CambiarSedeRequest;
use App\Http\Requests\Serologia\ConsultarContratoRequest;
use App\Http\Requests\Serologia\MarcarTomaRequest;
use App\Models\Contrato;
use App\Models\Sede;
use App\Models\Toma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaboratorioController extends Controller
{
    public const LABORATORIO_POR_DEFECTO = '20330025213';

    public function index(Request $request): Response
    {
        [$laboratorio, $sede] = $this->laboratorioYSede();

        return Inertia::render('laboratorio', [
            'laboratorios' => Sede::laboratorios()->map(fn (object $fila): array => [
                'codigo' => $fila->identificacion,
                'nombre' => $fila->alias,
            ])->all(),
            'sedes' => $laboratorio === null ? [] : Sede::deLaboratorio($laboratorio)
                ->map(fn (Sede $fila): array => [
                    'codigo' => $fila->codigo_sede,
                    'nombre' => $fila->descripcion_sede,
                ])->all(),
            'laboratorio' => $laboratorio,
            'sede' => $sede,
            'nombre_sede' => $laboratorio !== null && $sede !== null
                ? Sede::nombre($laboratorio, $sede) : null,
            'hoy' => $laboratorio !== null && $sede !== null
                ? Toma::tomadasHoy($laboratorio, $sede)->map(fn (Toma $fila): array => [
                    'numero_contrato' => $fila->numero_contrato_base,
                    'numero_toma' => (int) $fila->numero_toma,
                    'nombre' => $fila->nombre_completo,
                    'hora' => $fila->fecha_toma->format('H:i'),
                ])->all() : [],
            'resultado' => $request->session()->get('consulta_laboratorio'),
        ]);
    }

    public function consultar(ConsultarContratoRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $contrato = Contrato::buscarPorContratoDNI($datos['codigo']);

        if (! $contrato) {
            return to_route('laboratorio.index')->withErrors([
                'codigo' => 'No es clienta de Criocord, o el código está mal escrito. Prueba con el DNI.',
            ]);
        }

        $toma = $contrato->tomaActiva();

        if (! $toma) {
            return to_route('laboratorio.index')->withErrors([
                'codigo' => 'Este contrato todavía no está cargado en el sistema de tomas. No tomes la muestra: avisa a Criocord.',
            ]);
        }

        return to_route('laboratorio.index')->with(
            'consulta_laboratorio', $this->resultado($contrato, $toma)
        );
    }

    public function marcar(MarcarTomaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        [$laboratorio, $sede] = $this->laboratorioYSede();

        if ($laboratorio === null || $sede === null) {
            return to_route('laboratorio.index')->withErrors([
                'sede' => 'Selecciona un laboratorio y una sede activa antes de registrar la muestra.',
            ]);
        }

        $contrato = Contrato::buscarPorContratoDNI($datos['codigo']);
        $toma = $contrato === null ? null : Toma::query()
            ->where('no_cia', $contrato->no_cia)
            ->where('numero_contrato_base', $contrato->numero_base)
            ->where('numero_toma', $datos['numero_toma'])
            ->first();

        if (! $toma) {
            return to_route('laboratorio.index')->withErrors([
                'codigo' => 'No encontramos esa toma. Vuelve a escanear.',
            ]);
        }

        $recienMarcada = $toma->puedeTomarse() && $toma->marcarTomada($laboratorio, $sede);

        if (! $recienMarcada) {
            $toma = Toma::query()
                ->where('no_cia', $toma->no_cia)
                ->where('numero_contrato_base', $toma->numero_contrato_base)
                ->where('numero_toma', $toma->numero_toma)
                ->firstOrFail();
        }

        return to_route('laboratorio.index')->with(
            'consulta_laboratorio', $this->resultado($contrato, $toma, $recienMarcada)
        );
    }

    public function sede(CambiarSedeRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $sedes = Sede::deLaboratorio($datos['laboratorio']);

        if ($sedes->isEmpty()) {
            return to_route('laboratorio.index')->withErrors([
                'laboratorio' => 'Ese laboratorio no tiene sedes activas.',
            ]);
        }

        $sede = $sedes->firstWhere('codigo_sede', $datos['sede'] ?? null)
            ? $datos['sede'] : $sedes->first()->codigo_sede;

        $request->session()->put([
            'laboratorio.ruc' => $datos['laboratorio'],
            'laboratorio.sede' => $sede,
        ]);
        $request->session()->forget('consulta_laboratorio');

        return to_route('laboratorio.index');
    }

    /**
     * @return array{nombre: ?string, primer_nombre: ?string, numero_contrato: string, numero_toma: int, estado: string, fecha_toma: ?string, hora_toma: ?string, sede_toma: ?string, recien_marcada: bool}
     */
    private function resultado(Contrato $contrato, Toma $toma, bool $recienMarcada = false): array
    {
        return [
            'nombre' => $contrato->nombre_completo,
            'primer_nombre' => $contrato->primer_nombre,
            'numero_contrato' => $toma->numero_contrato_base,
            'numero_toma' => (int) $toma->numero_toma,
            'estado' => $toma->estado(),
            'fecha_toma' => $toma->fecha_toma?->toDateString(),
            'hora_toma' => $toma->fecha_toma?->format('H:i'),
            'sede_toma' => $toma->dondeSeTomo(),
            'recien_marcada' => $recienMarcada,
        ];
    }

    /** @return array{0: ?string, 1: ?string} */
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
