<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\RechazarLiquidacionRequest;
use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use App\Models\EventoLiquidacion;
use App\Models\LiquidacionComision;
use App\Reportes\Comisiones\AprobarLiquidacion;
use App\Reportes\Comisiones\DatosRealesComisiones;
use App\Reportes\Comisiones\DetalleLiquidacion;
use App\Reportes\Comisiones\EnviarLiquidacionAlPersonal;
use App\Reportes\Comisiones\LiquidacionesComisiones;
use App\Reportes\Comisiones\ProcesarLiquidacion;
use App\Reportes\Comisiones\RechazarLiquidacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class LiquidacionesComisionesController extends Controller
{
    public function __construct(private LiquidacionesComisiones $liquidaciones) {}

    public function mostrar(Request $request, string $liquidacion, DetalleLiquidacion $detalle): Response
    {
        return Inertia::render('comisiones-liquidacion', $detalle->pantalla($liquidacion, $request->user()));
    }

    public function procesar(Request $request, string $liquidacion, ProcesarLiquidacion $procesar, DatosRealesComisiones $datos): RedirectResponse
    {
        abort_if($datos->periodo($liquidacion) === null, 404);
        $procesar->ejecutar($liquidacion, $request->user());

        return $this->respuesta('Liquidación enviada a Gerencia', 'El período está pendiente de aprobación.');
    }

    public function aprobar(Request $request, string $liquidacion, AprobarLiquidacion $aprobar): RedirectResponse
    {
        $aprobar->ejecutar($this->version($request, $liquidacion), $request->user());

        return $this->respuesta('Liquidación aprobada', 'Administración ya puede enviar los correos al personal.');
    }

    public function rechazar(RechazarLiquidacionRequest $request, string $liquidacion, RechazarLiquidacion $rechazar): RedirectResponse
    {
        $rechazar->ejecutar($this->version($request, $liquidacion), $request->user(), $request->validated('comentario'));

        return $this->respuesta('Liquidación devuelta', 'Administración puede revisar el comentario y volver a procesarla.', 'aviso');
    }

    public function enviar(Request $request, string $liquidacion, EnviarLiquidacionAlPersonal $envio): RedirectResponse
    {
        $correos = $envio->solicitar($this->version($request, $liquidacion), $request->user());

        return $this->respuestaEnvio('Envío al personal en curso', $correos);
    }

    public function reenviar(Request $request, string $liquidacion, EnviarLiquidacionAlPersonal $envio): RedirectResponse
    {
        $correos = $envio->solicitar($this->version($request, $liquidacion), $request->user(), true);

        return $this->respuestaEnvio('Reenvío en curso', $correos);
    }

    private function version(Request $request, string $periodo): int
    {
        $activa = $this->liquidaciones->activa($periodo);
        if (! $activa || (int) $activa->id !== filter_var($request->input('liquidacion_id'), FILTER_VALIDATE_INT)) {
            throw LiquidacionComision::accionNoDisponible();
        }

        return (int) $activa->id;
    }

    public function correoGerencia(Request $request, string $liquidacion, DetalleLiquidacion $detalle): HttpResponse
    {
        [$foto, $eventos] = $this->datosCorreo($request, $liquidacion, $detalle);
        $proceso = $eventos['PROCESO'] ?? null;

        return response((new ComisionesParaAprobacion($foto, $proceso?->nombre ?? $request->user()->name,
            $this->fechaCorreo($proceso?->fecha, $foto['actualizada_en'])))->render());
    }

    public function correoAsesor(Request $request, string $liquidacion, string $asesor, DetalleLiquidacion $detalle): HttpResponse
    {
        [$foto, $eventos] = $this->datosCorreo($request, $liquidacion, $detalle);
        $persona = array_find($foto['asesores'], fn (array $persona): bool => (string) $persona['id'] === $asesor) ?? abort(404);

        return response((new DetalleComisionAsesor($persona, $foto['periodo'],
            $this->fechaCorreo($eventos['APROBACION']->fecha ?? null, $foto['actualizada_en'])))->render());
    }

    /** @return array{array, array<string, EventoLiquidacion>} */
    private function datosCorreo(Request $request, string $id, DetalleLiquidacion $detalle): array
    {
        $fila = $this->liquidaciones->activa($id);
        if ($fila) {
            return [$fila->foto(), $this->liquidaciones->eventos($fila->id)->keyBy('accion')->all()];
        }

        return [$detalle->mostrar($id, $request->user())['liquidacion'] ?? abort(404), []];
    }

    private function fechaCorreo(?string $fecha, string $alternativa): string
    {
        return $fecha === null ? $alternativa : CarbonImmutable::parse($fecha, config('reportes.zona_horaria'))->toIso8601String();
    }

    private function respuestaEnvio(string $titulo, int $correos): RedirectResponse
    {
        $cantidad = $correos === 1 ? 'Se enviará 1 correo' : "Se enviarán $correos correos";

        return $this->respuesta($titulo, "$cantidad en segundo plano. Esta pantalla se actualiza sola.");
    }

    private function respuesta(string $titulo, string $descripcion, string $tipo = 'exito'): RedirectResponse
    {
        Inertia::flash('comisiones', compact('tipo', 'titulo', 'descripcion'));

        return back();
    }
}
