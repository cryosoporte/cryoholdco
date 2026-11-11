<?php

namespace App\Serologia;

use App\Mail\RecordatorioSerologia;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Envía a un cliente en seguimiento el recordatorio para tomar su muestra serológica.
 */
class EnviarRecordatorioSerologia
{
    /**
     * Devuelve false si el proveedor de correo falló; el error queda reportado.
     *
     * @throws HttpException 404 si el contrato no está en el seguimiento de la empresa.
     * @throws ValidationException si la muestra ya se tomó o el cliente no tiene un correo válido.
     */
    public function enviar(string $empresa, string $numeroBase, string $identificacion): bool
    {
        $cliente = DB::table('VEN_CONTRATOS as C1')
            ->join('VEN_CLIENTES as C2', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C2.NO_CIA')
                    ->on('C1.IDENTIFICACION1', '=', 'C2.IDENTIFICACION');
            })
            ->where('C1.NO_CIA', $empresa)
            ->where('C1.NUMERO_BASE', $numeroBase)
            ->where('C2.IDENTIFICACION', $identificacion)
            ->whereIn('C1.SERVICIO', AlcanceSerologia::SERVICIOS)
            ->where('C1.FECHA_CONTRATO', '>=', AlcanceSerologia::fechaInicio())
            ->select(['C2.NOMBRE', 'C2.MAIL_CONTACTO'])
            ->first();

        abort_if($cliente === null, 404);

        if ($this->muestraYaTomadaORecibida($empresa, $numeroBase)) {
            throw ValidationException::withMessages([
                'recordatorio' => 'La muestra ya fue tomada o recibida. Actualiza los resultados.',
            ]);
        }

        $correo = trim($cliente->mail_contacto ?? '');

        if (Validator::make(['correo' => $correo], ['correo' => ['required', 'email']])->fails()) {
            throw ValidationException::withMessages([
                'recordatorio' => 'El cliente no tiene un correo de contacto válido registrado.',
            ]);
        }

        try {
            Mail::to($correo)->send(new RecordatorioSerologia($cliente->nombre));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    /**
     * Hay muestra si laboratorio registró la toma o el resultado, o si la colecta ya tiene serología.
     */
    private function muestraYaTomadaORecibida(string $empresa, string $numeroBase): bool
    {
        $tieneMuestra = DB::table('OPE_TOMAS_SEROLOGICAS')
            ->where('NO_CIA', $empresa)
            ->where('NUMERO_CONTRATO_BASE', $numeroBase)
            ->where(function (Builder $query): void {
                $query->whereNotNull('FECHA_TOMA')->orWhereNotNull('FECHA_RESULTADO');
            })
            ->exists();

        $tieneSerologia = DB::table('LAB_COLECTAS as L')
            ->join('VEN_CONTRATOS as C', function (JoinClause $join): void {
                $join->on('C.NO_CIA', '=', 'L.NO_CIA')
                    ->on('C.NUMERO_CONTRATO', '=', 'L.NUMERO_CONTRATO');
            })
            ->where('C.NO_CIA', $empresa)
            ->where('C.NUMERO_BASE', $numeroBase)
            ->whereNotNull('L.SEROLOGIA')
            ->exists();

        return $tieneMuestra || $tieneSerologia;
    }
}
