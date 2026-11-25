<?php

namespace App\Reportes\Comisiones;

use App\Models\EnvioLiquidacion;
use App\Models\EventoLiquidacion;
use App\Models\FuenteCarga;
use App\Models\LiquidacionComision;
use App\Models\User;
use Carbon\CarbonImmutable;

/** @phpstan-import-type Liquidacion from DatosRealesComisiones */
class DetalleLiquidacion
{
    public function __construct(
        private LiquidacionesComisiones $liquidaciones,
        private DatosRealesComisiones $datos,
        private CalcularComisiones $calculo,
        private CierreTrimestral $cierre,
    ) {}

    /** @return array{liquidacion: ?array, periodo: string, meses_pendientes: list<string>, envio_en_curso: bool, aviso_separacion: ?string, permisos: array<string, bool>} */
    public function mostrar(string $id, User $usuario): array
    {
        $periodo = $this->datos->periodo($id) ?? abort(404);
        $activa = $this->liquidaciones->activa($id);
        $pendientes = ! $activa && $periodo->periodicidad === Periodicidad::Trimestral
            ? $this->cierre->mesesPendientes($periodo) : [];
        $detalle = null;
        if ($pendientes === []) {
            $detalle = $activa?->foto() ?? $this->datos->desdeResultado($id, $this->calculo->calcular($periodo));
            unset($detalle['es_demo_activa']);
            $fila = $activa ?? $this->liquidaciones->ultimaRechazada($id);
            $detalle['liquidacion_id'] = $fila ? (int) $fila->id : null;
            if ($fila) {
                $detalle['estado'] = $fila->estadoPantalla();
                $detalle['historial'] = $this->historial($id);
                $detalle['envios'] = $this->envios($fila, $detalle);
            }
        }

        $procesada = $activa?->estado === LiquidacionComision::PROCESADA;
        $puedeAprobar = $procesada && $usuario->can('comisiones.aprobar');
        $puedeRechazar = $procesada && $usuario->can('comisiones.rechazar');
        $esProcesador = ($puedeAprobar || $puedeRechazar) && $this->liquidaciones->fueProcesadaPor($activa->id, $usuario);
        $cerrado = $periodo->hasta->toDateString() < FuenteCarga::ahora()->toDateString();
        $estadosEnvio = array_column($detalle['envios'] ?? [], 'estado');
        $enCurso = in_array('pendiente', $estadosEnvio, true);

        return [
            'liquidacion' => $detalle,
            'periodo' => $id,
            'meses_pendientes' => $pendientes,
            'envio_en_curso' => $enCurso,
            'aviso_separacion' => $esProcesador ? 'No puedes aprobar ni rechazar una liquidación que procesaste.' : null,
            'permisos' => [
                'puede_procesar' => ! $activa && $cerrado && $pendientes === [] && $usuario->can('comisiones.procesar'),
                'puede_aprobar' => $puedeAprobar && ! $esProcesador,
                'puede_rechazar' => $puedeRechazar && ! $esProcesador,
                'puede_enviar' => $activa?->estado === LiquidacionComision::APROBADA && $estadosEnvio === []
                    && $usuario->can('comisiones.enviar'),
                'puede_reenviar' => $activa?->estado === LiquidacionComision::ENVIADA && ! $enCurso
                    && in_array('fallido', $estadosEnvio, true) && $usuario->can('comisiones.enviar'),
            ],
        ];
    }

    /** @return list<array{tipo: string, por: string, fecha: string, comentario: ?string}> */
    private function historial(string $periodo): array
    {
        $versiones = LiquidacionComision::query()->select('id')->where('periodo', $periodo);

        return EventoLiquidacion::query()->whereIn('liq_id', $versiones)->orderBy('id')->get()
            ->map(fn (EventoLiquidacion $evento): array => [
                'tipo' => match ($evento->accion) {
                    'PROCESO' => 'procesada', 'APROBACION' => 'aprobada', 'RECHAZO' => 'rechazada',
                    'ENVIO' => 'enviada_personal', 'REENVIO' => 'reenvio',
                },
                'por' => $evento->nombre, 'fecha' => $this->fecha($evento->fecha), 'comentario' => $evento->comentario,
            ])->all();
    }

    /** @param Liquidacion $detalle
     * @return list<array{asesor_id: string, nombre: string, correo: string, estado: string, enviado_en: ?string, error: ?string}>
     */
    private function envios(LiquidacionComision $fila, array $detalle): array
    {
        $nombres = array_column($detalle['asesores'], 'nombre', 'id');

        return $this->liquidaciones->envios($fila->id)->map(fn (EnvioLiquidacion $envio): array => [
            'asesor_id' => (string) $envio->user_id, 'nombre' => $nombres[$envio->user_id],
            'correo' => $envio->correo,
            'estado' => match ($envio->estado) {
                EnvioLiquidacion::PENDIENTE => 'pendiente', EnvioLiquidacion::ENVIADO => 'enviado', EnvioLiquidacion::FALLIDO => 'fallido',
            },
            'enviado_en' => $envio->fecha_envio ? $this->fecha($envio->fecha_envio) : null, 'error' => $envio->error,
        ])->all();
    }

    private function fecha(string $fecha): string
    {
        return CarbonImmutable::parse($fecha, config('reportes.zona_horaria'))->toIso8601String();
    }
}
