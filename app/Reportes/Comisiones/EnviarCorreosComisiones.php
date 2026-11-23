<?php

namespace App\Reportes\Comisiones;

use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use LogicException;

/**
 * @phpstan-import-type Liquidacion from DatosRealesComisiones
 * @phpstan-import-type Asesor from DatosRealesComisiones
 */
class EnviarCorreosComisiones
{
    /** @param Liquidacion $liquidacion */
    public function gerencia(array $liquidacion, string $persona, string $fecha): void
    {
        $this->enviar($liquidacion['correo_gerencia'], new ComisionesParaAprobacion($liquidacion, $persona, $fecha));
    }

    /** @param Asesor $asesor */
    public function asesor(array $asesor, string $periodo, string $fechaAprobacion): void
    {
        $this->enviar($asesor['correo'], new DetalleComisionAsesor($asesor, $periodo, $fechaAprobacion));
    }

    public function pausar(): void
    {
        $milisegundos = max(0, (int) config('reportes.comisiones.pausa_envio_ms'));

        if ($milisegundos > 0) {
            usleep($milisegundos * 1000);
        }
    }

    private function enviar(string $destinatario, Mailable $correo): void
    {
        if (! filter_var($destinatario, FILTER_VALIDATE_EMAIL) || ! str_ends_with($destinatario, '@ejemplo.test')) {
            throw new LogicException('Los correos de comisiones solo admiten destinatarios de ejemplo.test.');
        }

        $mailer = config('mail.default');
        $opciones = config("mail.mailers.$mailer", []);
        $transporte = $opciones['transport'] ?? null;
        $esPrueba = app()->runningUnitTests() && $transporte === 'array';
        $host = strtolower((string) ($opciones['host'] ?? ''));
        $esSandbox = $transporte === 'smtp' && empty($opciones['url'])
            && in_array($host, ['sandbox.smtp.mailtrap.io', 'smtp.mailtrap.io'], true);

        if (! $esSandbox && ! $esPrueba) {
            throw new LogicException('El mailer por defecto de comisiones debe usar Mailtrap Sandbox.');
        }

        Mail::to($destinatario)->send($correo);
    }
}
