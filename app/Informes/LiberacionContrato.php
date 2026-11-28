<?php

namespace App\Informes;

/**
 * Cómo se presenta la liberación (VEN_CONTRATOS.LIBERACION) de los servicios de un contrato base.
 */
class LiberacionContrato
{
    public const APTO = 'APTO';

    public const NO_APTO = 'NO APTO';

    public const NO_LIBERADO = 'NO LIBERADO';

    /**
     * Resultado de un servicio: su liberación si laboratorio ya lo registró en LAB_COLECTAS_INFO
     * y NO LIBERADO si no. Es null cuando el contrato no tiene ese servicio.
     */
    public static function servicio(bool $tieneServicio, bool $registradoEnInfo, ?string $liberacion): ?string
    {
        if (! $tieneServicio) {
            return null;
        }

        if (! $registradoEnInfo) {
            return self::NO_LIBERADO;
        }

        return self::esApto($liberacion) ? self::APTO : self::NO_APTO;
    }

    /**
     * Estado global del contrato a partir de la liberación de sus servicios (un NULL cuenta como NO APTO).
     * Con un solo servicio se indica directamente su aptitud.
     *
     * @param  list<?string>  $liberaciones  la liberación de cada servicio que tiene el contrato
     */
    public static function estado(array $liberaciones): ?string
    {
        if ($liberaciones === []) {
            return null;
        }

        $aptos = count(array_filter($liberaciones, self::esApto(...)));

        if (count($liberaciones) === 1) {
            return $aptos === 1 ? self::APTO : self::NO_APTO;
        }

        return match (true) {
            $aptos === count($liberaciones) => 'AMBOS APTOS',
            $aptos === 0 => 'AMBOS NO APTOS',
            default => 'UNO NO ES APTO',
        };
    }

    private static function esApto(?string $liberacion): bool
    {
        return mb_strtoupper(trim((string) $liberacion)) === self::APTO;
    }
}
