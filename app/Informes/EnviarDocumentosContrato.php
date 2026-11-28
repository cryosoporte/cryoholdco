<?php

namespace App\Informes;

use App\Mail\DocumentosContrato;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Envía al correo de contacto del cliente los documentos que laboratorio guarda para su contrato.
 */
class EnviarDocumentosContrato
{
    public function __construct(
        private CarpetaDocumentos $carpeta,
        private ContratosInformes $contratos,
        private CuerpoCorreoContrato $cuerpo,
        private FilasInformes $filas,
    ) {}

    /**
     * Envía solo los archivos elegidos. Devuelve false si el proveedor de correo falló; el error queda reportado.
     *
     * @param  list<string>  $nombres  nombres de los archivos elegidos en la carpeta del contrato
     * @param  array{sangre?: ?string, tejido?: ?string}  $motivos  motivo de cada servicio NO APTO (claves de AlcanceInformes::MOTIVOS_NO_APTO)
     *
     * @throws HttpException 404 si el contrato no es de Informes de la empresa o no tiene carpeta.
     * @throws ValidationException si el cliente no tiene un correo válido, falta un archivo o el envío pesa demasiado.
     */
    public function enviar(string $empresa, string $autor, string $numeroBase, string $identificacion, array $nombres, ?string $usuario = null, array $motivos = []): bool
    {
        $correo = $this->correoDeContacto($empresa, $numeroBase, $identificacion);
        $cuerpo = $this->cuerpo->para($empresa, $numeroBase, $motivos);

        return $this->enviarArchivos($empresa, $autor, $numeroBase, $identificacion, $correo, $nombres, $usuario, $cuerpo);
    }

    /**
     * Envía los archivos de la carpeta del contrato que se envían por defecto en el envío masivo
     * (los que contienen algún texto de config('laboratorio.archivos_masivo') en su nombre).
     *
     * @throws HttpException 404 si el contrato no es de Informes de la empresa o no tiene carpeta, 409 si hay carpetas duplicadas.
     * @throws ValidationException si el cliente no tiene un correo válido, no hay archivos que enviar o el envío pesa demasiado.
     */
    public function enviarPredeterminados(string $empresa, string $autor, string $numeroBase, string $identificacion, ?string $usuario = null, array $motivos = []): bool
    {
        $correo = $this->correoDeContacto($empresa, $numeroBase, $identificacion);
        $cuerpo = $this->cuerpo->para($empresa, $numeroBase, $motivos);
        $existentes = array_column($this->carpeta->listar($empresa, $numeroBase), 'nombre');
        $nombres = array_values(array_filter($existentes, $this->esPredeterminado(...)));

        if ($existentes !== [] && $nombres === []) {
            throw ValidationException::withMessages([
                'archivos' => 'Ningún archivo de la carpeta es de los que se envían por defecto.',
            ]);
        }

        return $this->enviarArchivos($empresa, $autor, $numeroBase, $identificacion, $correo, $nombres, $usuario, $cuerpo);
    }

    private function esPredeterminado(string $nombre): bool
    {
        return Str::contains(Str::upper($nombre), array_map(Str::upper(...), config('laboratorio.archivos_masivo')));
    }

    private function correoDeContacto(string $empresa, string $numeroBase, string $identificacion): string
    {
        $cliente = $this->contratos->correoDelCliente($empresa, $numeroBase, $identificacion);

        abort_if($cliente === null, 404, 'El contrato no está en el listado de Informes de tu empresa.');

        $correo = trim($cliente->mail_contacto ?? '');

        if (Validator::make(['correo' => $correo], ['correo' => ['required', 'email']])->fails()) {
            throw ValidationException::withMessages([
                'documentos' => 'El cliente no tiene un correo de contacto válido registrado.',
            ]);
        }

        return $correo;
    }

    /**
     * @param  list<string>  $nombres
     * @param  array<string, mixed>  $cuerpo
     */
    private function enviarArchivos(string $empresa, string $autor, string $numeroBase, string $identificacion, string $correo, array $nombres, ?string $usuario, array $cuerpo): bool
    {
        if ($nombres === []) {
            throw ValidationException::withMessages([
                'archivos' => 'La carpeta del contrato no tiene archivos para enviar.',
            ]);
        }

        $archivos = $this->carpeta->seleccionar($empresa, $numeroBase, $nombres);
        $maximoMb = config('laboratorio.maximo_adjuntos_mb');

        if (array_sum(array_column($archivos, 'tamano')) > $maximoMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                'archivos' => "Los archivos superan los {$maximoMb} MB permitidos por correo. Envía menos archivos a la vez.",
            ]);
        }

        try {
            Mail::mailer(app()->isProduction() ? 'sac_peru' : null)->to(config('laboratorio.correo_prueba') ?: $correo)->send(
                new DocumentosContrato($empresa, $numeroBase, $archivos, $cuerpo),
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $this->registrarEnvio($empresa, $numeroBase, $identificacion, $usuario);

        Log::info('Documentos de contrato enviados por correo.', [
            'autor' => $autor,
            'empresa' => $empresa,
            'numero_base' => $numeroBase,
            'destinatario' => $correo,
            'archivos' => array_column($archivos, 'nombre'),
        ]);

        return true;
    }

    /**
     * Anota el envío en LAB_COLECTAS (si el entorno lo permite). El correo ya salió: un fallo al anotar
     * se reporta pero no se presenta como fallo del envío, para no provocar un reenvío duplicado.
     */
    private function registrarEnvio(string $empresa, string $numeroBase, string $identificacion, ?string $usuario): void
    {
        if (! config('laboratorio.registrar_envio')) {
            return;
        }

        try {
            $this->contratos->marcarEnviado($empresa, $numeroBase, $identificacion, $usuario);
            $this->filas->olvidar($empresa);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
