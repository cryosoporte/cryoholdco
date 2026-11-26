<?php

namespace App\Reportes\Comisiones;

use App\Models\EventoLiquidacion;
use App\Models\FuenteCarga;
use App\Models\FuenteDatos;
use App\Models\LiquidacionComision;
use App\Models\User;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcesarLiquidacion
{
    public function __construct(
        private DatosRealesComisiones $datos,
        private CalcularComisiones $calculo,
        private LiquidacionesComisiones $liquidaciones,
        private EnviarCorreosComisiones $correo,
    ) {}

    public function ejecutar(string $id, User $usuario): LiquidacionComision
    {
        Gate::forUser($usuario)->authorize('comisiones.procesar');
        $periodo = $this->datos->periodo($id);
        $fecha = FuenteCarga::ahora();
        if ($periodo === null || ($periodo->periodicidad === Periodicidad::Mensual && $periodo->hasta->toDateString() >= $fecha->toDateString())) {
            throw LiquidacionComision::accionNoDisponible();
        }

        return DB::transaction(function () use ($id, $usuario, $periodo, $fecha): LiquidacionComision {
            if ($periodo->periodicidad === Periodicidad::Mensual) {
                // ImportarFuente bloquea la misma fila antes de reemplazar las facturas y completar la carga.
                FuenteDatos::query()->whereKey(FuenteFacturasOracle::CODIGO)->lockForUpdate()->get(['codigo']);
            }
            if ($this->liquidaciones->activa($id) !== null) {
                throw $this->yaProcesado();
            }

            $cargaId = FuenteCarga::query()->where('codigo_fuente', FuenteFacturasOracle::CODIGO)
                ->where('estado', FuenteCarga::COMPLETADA)->orderByDesc('fecha_fin')->orderByDesc('id')->value('id');
            $resultado = $this->calculo->calcular($periodo);
            $foto = $this->datos->desdeResultado($id, $resultado);
            if ($foto['total'] !== $resultado->total()) {
                throw ValidationException::withMessages(['accion' => 'El total del desglose no coincide con el detalle de comisiones.']);
            }
            $foto['estado'] = 'enviada';
            $foto['actualizada_en'] = $fecha->toIso8601String();

            try {
                $liquidacion = LiquidacionComision::guardarFoto($foto, $cargaId, $fecha);
            } catch (UniqueConstraintViolationException $error) {
                $mensaje = strtolower($error->getPrevious()?->getMessage() ?? $error->getMessage());
                if (str_contains($mensaje, 'com_liq_activa_ux') || str_contains($mensaje, 'com_liq.periodo')) {
                    throw $this->yaProcesado();
                }
                throw $error;
            }

            $this->guardarDetalle($liquidacion, $resultado);
            EventoLiquidacion::registrar($liquidacion->id, 'PROCESO', $usuario, $fecha);

            $fotoGuardada = $liquidacion->foto();
            try {
                $this->correo->gerencia($fotoGuardada, $usuario->name, $fecha->toIso8601String());
            } catch (Throwable $error) {
                report($error);
                throw ValidationException::withMessages(['accion' => 'No se pudo enviar el correo a gerencia. Vuelve a intentarlo.']);
            }

            return $liquidacion;
        });
    }

    private function guardarDetalle(LiquidacionComision $liquidacion, ResultadoComisiones $resultado): void
    {
        $detalle = [];
        $contratos = [];
        foreach ($resultado->comisiones as $comision) {
            $regla = $comision->resultado;
            $meta = $regla->meta;
            $asesor = $comision->asesor;
            $detalle[] = [
                'liq_id' => $liquidacion->id, 'user_id' => $asesor->id, 'meta_id' => $meta->id,
                'nombre' => $asesor->nombre, 'codigo_netsuite' => $asesor->codigoNetsuite, 'rol_netsuite' => $asesor->rol,
                'linea' => $meta->linea, 'atribucion' => $meta->atribucion->value, 'tipo_meta' => $meta->tipo->value,
                'base_tramo' => $meta->baseTramo->value, 'minimo' => $meta->minimo, 'cantidad' => $regla->cantidad,
                'monto' => $regla->monto, 'tramo_desde' => $regla->tramo?->desde, 'tramo_hasta' => $regla->tramo?->hasta,
                'tramo_valor' => $regla->tramo?->valor, 'comision' => $comision->comision(), 'adelanto' => $comision->adelanto,
                'pendiente' => $comision->adelanto === null ? null : $comision->pendiente(),
                'a_pagar' => $comision->adelanto === null ? $comision->comision() : $comision->pendiente(),
            ];
            foreach ($comision->contratos as $contrato) {
                $morosa = $contrato instanceof AnualidadMorosa;
                $contratos[] = [
                    'liq_id' => $liquidacion->id, 'user_id' => $asesor->id, 'linea' => $meta->linea,
                    'numero_contrato' => $contrato->contrato, 'servicio' => $morosa ? $contrato->servicio : null,
                    'fecha_emision' => $contrato->fechaEmision.' 00:00:00', 'monto' => $contrato->monto,
                    'filas' => $morosa ? $contrato->anios : $contrato->filas,
                ];
            }
        }

        foreach (['detalle' => $detalle, 'contrato' => $contratos] as $sufijo => $filas) {
            foreach (array_chunk($filas, max(1, (int) config('reportes.filas_por_bloque'))) as $bloque) {
                DB::table($liquidacion->getTable().'_'.$sufijo)->insert($bloque);
            }
        }
    }

    private function yaProcesado(): ValidationException
    {
        return ValidationException::withMessages(['accion' => 'Este período ya fue procesado.']);
    }
}
