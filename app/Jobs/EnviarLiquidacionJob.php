<?php

namespace App\Jobs;

use App\Reportes\Comisiones\EnviarLiquidacionAlPersonal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envía en segundo plano los correos pendientes de una liquidación, con la
 * pausa de reportes.comisiones.pausa_envio_ms entre uno y otro. Usa el mismo
 * worker que las cargas de fuentes (conexión redis_reportes).
 */
class EnviarLiquidacionJob implements ShouldQueue
{
    use Queueable;

    public const TIEMPO_LIMITE = 900;

    public int $timeout = self::TIEMPO_LIMITE;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $liquidacionId)
    {
        $this->onConnection('redis_reportes');
    }

    public function handle(EnviarLiquidacionAlPersonal $envio): void
    {
        $envio->procesarPendientes($this->liquidacionId);
    }

    /**
     * Si el job se interrumpe, los correos que no se alcanzaron a enviar
     * quedan como fallidos para reenviarlos desde la pantalla.
     */
    public function failed(?Throwable $error): void
    {
        app(EnviarLiquidacionAlPersonal::class)->interrumpir($this->liquidacionId);
    }
}
