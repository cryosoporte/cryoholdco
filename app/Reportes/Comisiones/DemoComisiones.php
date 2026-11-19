<?php

namespace App\Reportes\Comisiones;

use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @phpstan-import-type Liquidacion from DatosEjemploComisiones
 * @phpstan-import-type Resumen from DatosEjemploComisiones
 * @phpstan-import-type Estado from DatosEjemploComisiones
 * @phpstan-import-type Envio from DatosEjemploComisiones
 * @phpstan-import-type Evento from DatosEjemploComisiones
 *
 * @phpstan-type Permisos array{puede_procesar: bool, puede_aprobar: bool, puede_rechazar: bool, puede_reenviar: bool}
 */
class DemoComisiones
{
    private const CLAVE = 'reportes.comisiones_demo';

    private const ACCION_NO_DISPONIBLE = 'Esta acción no está disponible en el estado actual.';

    public function __construct(
        private readonly Session $sesion,
        private readonly DatosComisiones $datos,
        private readonly EnviarComisionesDemo $correo,
    ) {}

    public function rol(): string
    {
        return $this->sesion->get(self::CLAVE.'.rol', 'analista');
    }

    public function cambiarRol(string $rol): void
    {
        $this->sesion->put(self::CLAVE.'.rol', $rol);
    }

    /** @return list<Resumen> */
    public function liquidaciones(): array
    {
        $resumenes = $this->datos->liquidaciones($this->idActivo());
        $resumenes[0] = [...$resumenes[0], ...$this->cambiosDeResumen()];

        return $resumenes;
    }

    /** @return Liquidacion */
    public function liquidacion(string $id): array
    {
        $liquidacion = $this->datos->liquidacion($id, $this->idActivo()) ?? abort(404);

        if (! $liquidacion['es_demo_activa']) {
            return $liquidacion;
        }

        return [
            ...$liquidacion,
            ...$this->cambiosDeResumen(),
            'historial' => $this->sesion->get(self::CLAVE.'.historial', []),
            'envios' => $this->sesion->get(self::CLAVE.'.envios', []),
        ];
    }

    /**
     * @param  Liquidacion  $liquidacion
     * @return Permisos
     */
    public function permisos(array $liquidacion): array
    {
        $activa = $liquidacion['es_demo_activa'];
        $estado = $liquidacion['estado'];
        $esGerenta = $this->rol() === 'gerenta';
        $puedeRevisar = $activa && $esGerenta && $estado === 'enviada';

        return [
            'puede_procesar' => $activa && ! $esGerenta && in_array($estado, ['borrador', 'rechazada'], true),
            'puede_aprobar' => $puedeRevisar,
            'puede_rechazar' => $puedeRevisar,
            'puede_reenviar' => $activa && $estado === 'enviada_personal'
                && in_array('fallido', array_column($liquidacion['envios'], 'estado'), true),
        ];
    }

    public function nueva(string $id, string $persona): void
    {
        $this->exigir($this->rol() === 'analista');
        $periodo = $this->datos->liquidacion($id, $id) ?? abort(404);

        $this->sesion->put(self::CLAVE, [
            'rol' => 'analista',
            'liquidacion_activa' => ['id' => $id, 'periodo' => $periodo['periodo']],
            'estado' => 'borrador',
            'historial' => [],
            'envios' => [],
            'comentario_rechazo' => null,
        ]);

        $this->registrar('generada', $persona);
    }

    public function reiniciar(): void
    {
        $this->sesion->forget(self::CLAVE);
    }

    public function procesar(string $id, string $persona): void
    {
        $liquidacion = $this->liquidacion($id);
        $this->exigir($this->permisos($liquidacion)['puede_procesar']);
        $fecha = $this->ahora();

        try {
            $this->correo->gerencia($liquidacion, $persona, $fecha);
        } catch (Throwable $error) {
            report($error);

            throw ValidationException::withMessages([
                'accion' => 'No se pudo enviar el correo a gerencia. Vuelve a intentarlo.',
            ]);
        }

        $this->sesion->put(self::CLAVE.'.estado', 'enviada');
        $this->sesion->put(self::CLAVE.'.comentario_rechazo', null);
        $this->registrar('procesada', $persona, fecha: $fecha);
    }

    public function rechazar(string $id, string $persona, string $comentario): void
    {
        $liquidacion = $this->liquidacion($id);
        $this->exigir($this->permisos($liquidacion)['puede_rechazar']);

        $this->sesion->put(self::CLAVE.'.estado', 'rechazada');
        $this->sesion->put(self::CLAVE.'.comentario_rechazo', $comentario);
        $this->registrar('rechazada', $persona, $comentario);
    }

    /** @return array{enviados: int, total: int} */
    public function aprobar(string $id, string $persona): array
    {
        $liquidacion = $this->liquidacion($id);
        $this->exigir($this->permisos($liquidacion)['puede_aprobar']);

        $fecha = $this->ahora();
        $this->sesion->put(self::CLAVE.'.estado', 'aprobada');
        $this->registrar('aprobada', $persona, fecha: $fecha);

        $envios = array_map($this->datos->envioPendiente(...), $liquidacion['asesores']);
        $this->sesion->put(self::CLAVE.'.envios', $envios);
        $resultado = $this->enviarAlPersonal($liquidacion, $envios, 'pendiente', $fecha);

        $this->sesion->put(self::CLAVE.'.estado', 'enviada_personal');
        $this->registrar('enviada_personal', 'Sistema');

        return $resultado;
    }

    /** @return array{enviados: int, total: int} */
    public function reenviar(string $id, string $persona): array
    {
        $liquidacion = $this->liquidacion($id);
        $this->exigir($this->permisos($liquidacion)['puede_reenviar']);

        $fechaAprobacion = array_find($liquidacion['historial'], fn (array $evento): bool => $evento['tipo'] === 'aprobada')['fecha'];
        $resultado = $this->enviarAlPersonal($liquidacion, $liquidacion['envios'], 'fallido', $fechaAprobacion);
        $this->registrar('reenvio', $persona);

        return $resultado;
    }

    /**
     * @param  Liquidacion  $liquidacion
     * @param  list<Envio>  $envios
     * @return array{enviados: int, total: int}
     */
    private function enviarAlPersonal(array $liquidacion, array $envios, string $estadoPendiente, string $fechaAprobacion): array
    {
        $asesores = array_column($liquidacion['asesores'], null, 'id');
        $requierePausa = false;

        foreach ($envios as $indice => $envio) {
            if ($envio['estado'] !== $estadoPendiente) {
                continue;
            }

            try {
                if ($requierePausa) {
                    $this->correo->pausar();
                }

                $this->correo->asesor($asesores[$envio['asesor_id']], $liquidacion['periodo'], $fechaAprobacion);

                $envios[$indice] = [...$envio, 'estado' => 'enviado', 'enviado_en' => $this->ahora(), 'error' => null];
            } catch (Throwable $error) {
                report($error);
                $envios[$indice] = [...$envio, 'estado' => 'fallido', 'error' => 'No se pudo enviar el correo.'];
            }

            $requierePausa = true;
        }

        $this->sesion->put(self::CLAVE.'.envios', $envios);

        return [
            'enviados' => count(array_filter($envios, fn (array $envio): bool => $envio['estado'] === 'enviado')),
            'total' => count($envios),
        ];
    }

    private function idActivo(): string
    {
        return $this->sesion->get(self::CLAVE.'.liquidacion_activa.id', $this->datos->periodoInicial());
    }

    /** @return array{estado?: Estado, actualizada_en?: string} */
    private function cambiosDeResumen(): array
    {
        $historial = $this->sesion->get(self::CLAVE.'.historial', []);

        return $historial === [] ? [] : [
            'estado' => $this->sesion->get(self::CLAVE.'.estado'),
            'actualizada_en' => $historial[array_key_last($historial)]['fecha'],
        ];
    }

    /** @param Evento['tipo'] $tipo */
    private function registrar(string $tipo, string $persona, ?string $comentario = null, ?string $fecha = null): void
    {
        $this->sesion->push(self::CLAVE.'.historial', [
            'tipo' => $tipo,
            'fecha' => $fecha ?? $this->ahora(),
            'por' => $persona,
            'comentario' => $comentario,
        ]);
    }

    private function ahora(): string
    {
        return now(config('reportes.zona_horaria'))->toIso8601String();
    }

    private function exigir(bool $permitida): void
    {
        if (! $permitida) {
            throw ValidationException::withMessages(['accion' => self::ACCION_NO_DISPONIBLE]);
        }
    }
}
