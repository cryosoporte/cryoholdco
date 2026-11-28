<?php

namespace App\Informes;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Envía a varios clientes los documentos por defecto de la carpeta de su contrato.
 *
 * Cada contrato es independiente: el que no se puede enviar (sin carpeta, sin correo válido,
 * demasiado pesado...) se omite con su motivo y los demás siguen. Una misma carpeta
 * (contrato base) se envía una sola vez al mismo cliente aunque se hayan elegido varios contratos.
 */
class EnviarDocumentosMasivo
{
    public function __construct(private EnviarDocumentosContrato $envio) {}

    /**
     * @param  list<array{numero_base: string, identificacion: string}>  $contratos
     * @return array{enviados: list<string>, omitidos: list<array{numero_base: string, motivo: string}>}
     */
    public function enviar(string $empresa, string $autor, array $contratos, ?string $usuario = null): array
    {
        $enviados = [];
        $omitidos = [];
        $procesados = [];

        foreach ($contratos as $contrato) {
            $clave = $contrato['numero_base']."\0".$contrato['identificacion'];

            if (isset($procesados[$clave])) {
                continue;
            }

            $procesados[$clave] = true;
            $motivo = $this->intentarEnviar($empresa, $autor, $contrato, $usuario);

            if ($motivo === null) {
                $enviados[] = $contrato['numero_base'];
            } else {
                $omitidos[] = ['numero_base' => $contrato['numero_base'], 'motivo' => $motivo];
            }
        }

        return ['enviados' => $enviados, 'omitidos' => $omitidos];
    }

    /**
     * @param  array{numero_base: string, identificacion: string}  $contrato
     * @return string|null el motivo por el que no se envió, o null si se envió
     */
    private function intentarEnviar(string $empresa, string $autor, array $contrato, ?string $usuario): ?string
    {
        try {
            $enviado = $this->envio->enviarPredeterminados($empresa, $autor, $contrato['numero_base'], $contrato['identificacion'], $usuario);
        } catch (ValidationException $exception) {
            return (string) collect($exception->errors())->flatten()->first();
        } catch (HttpException $exception) {
            return $exception->getMessage() !== '' ? $exception->getMessage() : 'No se pudo acceder al contrato.';
        }

        return $enviado ? null : 'El proveedor de correo no pudo enviar el mensaje.';
    }
}
