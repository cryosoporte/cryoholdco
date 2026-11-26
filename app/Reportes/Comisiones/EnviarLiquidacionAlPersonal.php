<?php

namespace App\Reportes\Comisiones;

use App\Jobs\EnviarLiquidacionJob;
use App\Models\EnvioLiquidacion;
use App\Models\EventoLiquidacion;
use App\Models\FuenteCarga;
use App\Models\LiquidacionComision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Envío del detalle de comisión a cada colaborador de una liquidación
 * aprobada. La petición web solo deja los correos pendientes y encola
 * EnviarLiquidacionJob; el worker los envía uno por uno.
 *
 * Mientras haya correos pendientes, el envío está en curso: no se puede
 * volver a enviar ni reenviar. Al terminar (o si el job se interrumpe), la
 * liquidación pasa a ENVIADA y los fallidos se pueden reenviar.
 *
 * @phpstan-import-type Asesor from DatosRealesComisiones
 */
class EnviarLiquidacionAlPersonal
{
    public const ERROR_ENVIO = 'No se pudo enviar el correo.';

    public const ERROR_INTERRUPCION = 'El envío se interrumpió antes de este correo.';

    public function __construct(private EnviarCorreosComisiones $correo) {}

    /**
     * Deja pendientes los correos (todos en el primer envío, los fallidos en
     * un reenvío), registra el evento y encola el envío.
     *
     * @return int correos encolados
     */
    public function solicitar(int $id, User $usuario, bool $reenvio = false): int
    {
        Gate::forUser($usuario)->authorize('comisiones.enviar');

        $pendientes = DB::transaction(function () use ($id, $usuario, $reenvio): int {
            $liquidacion = LiquidacionComision::bloquearEnEstado($id, $reenvio ? LiquidacionComision::ENVIADA : LiquidacionComision::APROBADA);
            $envios = EnvioLiquidacion::query()->where('liq_id', $id);

            if ($reenvio) {
                $pendientes = (clone $envios)->where('estado', EnvioLiquidacion::FALLIDO)
                    ->update(['estado' => EnvioLiquidacion::PENDIENTE, 'error' => null]);
            } else {
                if ((clone $envios)->exists()) {
                    throw LiquidacionComision::accionNoDisponible();
                }

                $asesores = $liquidacion->foto()['asesores'];
                foreach ($asesores as $asesor) {
                    EnvioLiquidacion::query()->insert([
                        'liq_id' => $id, 'user_id' => $asesor['id'], 'correo' => $asesor['correo'],
                        'estado' => EnvioLiquidacion::PENDIENTE, 'intentos' => 0,
                    ]);
                }
                $pendientes = count($asesores);
            }

            if ($pendientes === 0) {
                throw LiquidacionComision::accionNoDisponible();
            }

            EventoLiquidacion::registrar($id, $reenvio ? 'REENVIO' : 'ENVIO', $usuario, FuenteCarga::ahora());

            return $pendientes;
        });

        try {
            Bus::dispatch(new EnviarLiquidacionJob($id));
        } catch (Throwable $error) {
            report($error);
            $this->interrumpir($id, 'No se pudo iniciar el envío.');

            throw ValidationException::withMessages([
                'accion' => 'No se pudo iniciar el envío. Vuelve a intentarlo con «Reenviar fallidos».',
            ]);
        }

        return $pendientes;
    }

    /**
     * Lo ejecuta el worker: envía los correos pendientes con la pausa
     * configurada y, al terminar, marca la liquidación como ENVIADA.
     */
    public function procesarPendientes(int $id): void
    {
        $liquidacion = LiquidacionComision::query()->find($id);

        if ($liquidacion === null || ! in_array($liquidacion->estado, [LiquidacionComision::APROBADA, LiquidacionComision::ENVIADA], true)) {
            return;
        }

        $foto = $liquidacion->foto();
        $aprobacion = EventoLiquidacion::query()->where('liq_id', $id)->where('accion', 'APROBACION')->sole();
        $fecha = CarbonImmutable::parse($aprobacion->fecha, config('reportes.zona_horaria'))->toIso8601String();
        $asesores = array_column($foto['asesores'], null, 'id');
        $pendientes = EnvioLiquidacion::query()->where('liq_id', $id)
            ->where('estado', EnvioLiquidacion::PENDIENTE)->orderBy('user_id')->get();

        foreach ($pendientes as $envio) {
            $this->correo->pausar();
            $this->intentarEnvio($envio, $asesores[$envio->user_id], $foto['periodo'], $fecha);
        }

        $this->marcarEnviada($id);
    }

    /**
     * Los correos que quedaron pendientes pasan a fallidos (para reenviarlos)
     * y la liquidación pasa a ENVIADA.
     */
    public function interrumpir(int $id, string $error = self::ERROR_INTERRUPCION): void
    {
        DB::transaction(function () use ($id, $error): void {
            EnvioLiquidacion::query()->where('liq_id', $id)->where('estado', EnvioLiquidacion::PENDIENTE)
                ->update(['estado' => EnvioLiquidacion::FALLIDO, 'error' => $error]);
        });

        $this->marcarEnviada($id);
    }

    private function marcarEnviada(int $id): void
    {
        LiquidacionComision::query()->whereKey($id)->where('estado', LiquidacionComision::APROBADA)
            ->update(['estado' => LiquidacionComision::ENVIADA]);
    }

    /**
     * Cada correo confirmado queda guardado aunque se interrumpa el resto del
     * envío. El contador de intentos evita que dos procesos envíen el mismo.
     *
     * @param  Asesor  $asesor
     */
    private function intentarEnvio(EnvioLiquidacion $envio, array $asesor, string $periodo, string $fecha): void
    {
        DB::transaction(function () use ($envio, $asesor, $periodo, $fecha): void {
            $fila = EnvioLiquidacion::query()->where('liq_id', $envio->liq_id)->where('user_id', $envio->user_id);
            $reservado = (clone $fila)->where('estado', EnvioLiquidacion::PENDIENTE)
                ->where('intentos', $envio->intentos)->increment('intentos');

            if ($reservado === 1) {
                $fila->update($this->enviarAsesor($asesor, $periodo, $fecha));
            }
        });
    }

    /**
     * @param  Asesor  $asesor
     * @return array{estado: string, fecha_envio: ?string, error: ?string}
     */
    private function enviarAsesor(array $asesor, string $periodo, string $aprobacion): array
    {
        try {
            $this->correo->asesor($asesor, $periodo, $aprobacion);
        } catch (Throwable $error) {
            report($error);

            return ['estado' => EnvioLiquidacion::FALLIDO, 'fecha_envio' => null, 'error' => self::ERROR_ENVIO];
        }

        return ['estado' => EnvioLiquidacion::ENVIADO, 'fecha_envio' => FuenteCarga::ahora()->toDateTimeString(), 'error' => null];
    }
}
