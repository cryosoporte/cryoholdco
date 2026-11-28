<?php

namespace App\Informes;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Los contratos de una empresa que entran en Informes: con su cliente, con registro de colecta
 * en LAB_COLECTAS (inner join), en un estado de AlcanceInformes::ESTADOS_CONTRATO y de un servicio de
 * AlcanceInformes::SERVICIOS. No exige estar en LAB_COLECTAS_INFO: un servicio sin registro allí aparece como
 * NO LIBERADO (ver LiberacionContrato).
 */
class ContratosInformes
{
    public function consulta(string $empresa): Builder
    {
        return DB::table('VEN_CONTRATOS as C1')
            ->join('VEN_CLIENTES as C2', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C2.NO_CIA')
                    ->on('C1.IDENTIFICACION1', '=', 'C2.IDENTIFICACION');
            })
            ->join('LAB_COLECTAS as C3', function (JoinClause $join): void {
                $join->on('C1.NO_CIA', '=', 'C3.NO_CIA')
                    ->on('C1.NUMERO_CONTRATO', '=', 'C3.NUMERO_CONTRATO');
            })
            ->where('C1.NO_CIA', $empresa)
            ->whereDate('C3.FECHA_REGISTRO', '>=', '2023-01-01')
            ->whereIn('C1.ESTADO_CONTRATO', AlcanceInformes::ESTADOS_CONTRATO)
            ->whereIn('C1.SERVICIO', AlcanceInformes::SERVICIOS);
    }

    /**
     * Los titulares del contrato (el 1 y, si lo hay, el 2) con su nombre completo, celular y correo.
     *
     * @return list<array{titular: int, nombre: string, celular: ?string, correo: ?string}>
     */
    public function titulares(string $empresa, string $numeroBase): array
    {
        $contrato = $this->consulta($empresa)
            ->where('C1.NUMERO_BASE', $numeroBase)
            ->select(['C1.IDENTIFICACION1', 'C1.IDENTIFICACION2'])
            ->first();

        if ($contrato === null) {
            return [];
        }

        $identificaciones = array_filter(
            [1 => $contrato->identificacion1, 2 => $contrato->identificacion2],
            fn ($valor): bool => filled($valor),
        );
        $clientes = DB::table('VEN_CLIENTES')
            ->where('NO_CIA', $empresa)
            ->whereIn('IDENTIFICACION', array_values($identificaciones))
            ->get(['IDENTIFICACION', 'NOMBRE', 'CELULAR_CONTACTO', 'MAIL_CONTACTO'])
            ->keyBy('identificacion');

        $titulares = [];

        foreach ($identificaciones as $numero => $identificacion) {
            $cliente = $clientes->get($identificacion);

            if ($cliente !== null) {
                $titulares[] = [
                    'titular' => $numero,
                    'nombre' => trim((string) $cliente->nombre),
                    'celular' => trim((string) $cliente->celular_contacto) ?: null,
                    'correo' => trim((string) $cliente->mail_contacto) ?: null,
                ];
            }
        }

        return $titulares;
    }

    /**
     * Anota en LAB_COLECTAS que los documentos del contrato ya se enviaron al cliente.
     */
    public function marcarEnviado(string $empresa, string $numeroBase, string $identificacion, ?string $usuario): void
    {
        $numeros = DB::table('VEN_CONTRATOS')
            ->where('NO_CIA', $empresa)
            ->where('NUMERO_BASE', $numeroBase)
            ->where('IDENTIFICACION1', $identificacion)
            ->select('NUMERO_CONTRATO');

        DB::table('LAB_COLECTAS')
            ->where('NO_CIA', $empresa)
            ->whereIn('NUMERO_CONTRATO', $numeros)
            ->update([
                'FLAG_ENVIO_CORREO' => AlcanceInformes::FLAG_ENVIADO,
                'ENVIO_FECHA_CORREO' => now(),
                'USUARIO_ENVIO' => $usuario,
            ]);
    }

    /**
     * El correo de contacto del cliente del contrato, o null si el contrato no es de Informes
     * de la empresa o la identificación no corresponde a su cliente.
     */
    public function correoDelCliente(string $empresa, string $numeroBase, string $identificacion): ?object
    {
        return $this->consulta($empresa)
            ->where('C1.NUMERO_BASE', $numeroBase)
            ->where('C2.IDENTIFICACION', $identificacion)
            ->select(['C2.MAIL_CONTACTO'])
            ->first();
    }

    public function existe(string $empresa, string $numeroBase): bool
    {
        return $this->consulta($empresa)->where('C1.NUMERO_BASE', $numeroBase)->exists();
    }
}
