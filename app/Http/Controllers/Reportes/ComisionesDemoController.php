<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\CambiarRolDemoRequest;
use App\Http\Requests\Reportes\NuevaLiquidacionRequest;
use App\Http\Requests\Reportes\RechazarLiquidacionRequest;
use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use App\Reportes\Comisiones\DemoComisiones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComisionesDemoController extends Controller
{
    public function mostrar(string $liquidacion, DemoComisiones $demo): Response
    {
        $detalle = $demo->liquidacion($liquidacion);

        return Inertia::render('comisiones-liquidacion', [
            'liquidacion' => $detalle,
            'rol' => $demo->rol(),
            'permisos' => $demo->permisos($detalle),
            'fuente' => ComisionesController::actualizacionFuente(),
        ]);
    }

    public function rol(CambiarRolDemoRequest $request, DemoComisiones $demo): RedirectResponse
    {
        $demo->cambiarRol($request->validated('rol'));
        $this->flash('Rol de la demo actualizado', $demo->rol() === 'analista' ? 'Ver como analista.' : 'Ver como gerenta.');

        return back();
    }

    public function reiniciar(DemoComisiones $demo): RedirectResponse
    {
        $demo->reiniciar();
        $this->flash('Demo reiniciada', 'Octubre 2026 está en borrador.');

        return to_route('reportes.comisiones');
    }

    public function nueva(NuevaLiquidacionRequest $request, DemoComisiones $demo): RedirectResponse
    {
        $periodo = $request->validated('periodo');
        $demo->nueva($periodo, $request->user()->name);
        $this->flash('Liquidación generada', 'Ya puedes revisar el desglose por cargo.');

        return to_route('reportes.comisiones.liquidaciones.mostrar', $periodo);
    }

    public function procesar(Request $request, string $liquidacion, DemoComisiones $demo): RedirectResponse
    {
        $demo->procesar($liquidacion, $request->user()->name);
        $this->flash('Liquidación enviada a gerencia', 'El período está pendiente de aprobación.');

        return back();
    }

    public function aprobar(Request $request, string $liquidacion, DemoComisiones $demo): RedirectResponse
    {
        $this->flashEnvios('Liquidación aprobada', $demo->aprobar($liquidacion, $request->user()->name));

        return back();
    }

    public function rechazar(RechazarLiquidacionRequest $request, string $liquidacion, DemoComisiones $demo): RedirectResponse
    {
        $demo->rechazar($liquidacion, $request->user()->name, $request->validated('comentario'));
        $this->flash('Liquidación devuelta', 'El analista puede revisar el comentario y volver a procesarla.', 'aviso');

        return back();
    }

    public function reenviar(Request $request, string $liquidacion, DemoComisiones $demo): RedirectResponse
    {
        $this->flashEnvios('Reenvío completado', $demo->reenviar($liquidacion, $request->user()->name));

        return back();
    }

    public function correoGerencia(Request $request, string $liquidacion, DemoComisiones $demo): HttpResponse
    {
        $detalle = $demo->liquidacion($liquidacion);
        $procesada = array_find(array_reverse($detalle['historial']), fn (array $evento): bool => $evento['tipo'] === 'procesada');
        $correo = new ComisionesParaAprobacion(
            $detalle,
            $procesada['por'] ?? $request->user()->name,
            $procesada['fecha'] ?? $detalle['actualizada_en'],
        );

        return response($correo->render());
    }

    public function correoAsesor(string $liquidacion, string $asesor, DemoComisiones $demo): HttpResponse
    {
        $detalle = $demo->liquidacion($liquidacion);
        $destinatario = array_find($detalle['asesores'], fn (array $persona): bool => $persona['id'] === $asesor) ?? abort(404);
        $aprobada = array_find($detalle['historial'], fn (array $evento): bool => $evento['tipo'] === 'aprobada');
        $correo = new DetalleComisionAsesor($destinatario, $detalle['periodo'], $aprobada['fecha'] ?? $detalle['actualizada_en']);

        return response($correo->render());
    }

    /** @param array{enviados: int, total: int} $resultado */
    private function flashEnvios(string $titulo, array $resultado): void
    {
        $enviados = $resultado['enviados'];
        $total = $resultado['total'];
        $fallidos = $total - $enviados;
        $descripcion = "$enviados de $total enviados";

        if ($fallidos > 0) {
            $descripcion .= $fallidos === 1 ? ' · 1 falló, puedes reenviarlo' : " · $fallidos fallaron, puedes reenviarlos";
        }

        $this->flash($titulo, $descripcion, $fallidos > 0 ? 'aviso' : 'exito');
    }

    private function flash(string $titulo, string $descripcion, string $tipo = 'exito'): void
    {
        Inertia::flash('comisiones', compact('tipo', 'titulo', 'descripcion'));
    }
}
