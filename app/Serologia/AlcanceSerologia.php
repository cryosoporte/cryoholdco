<?php

namespace App\Serologia;

use Carbon\CarbonImmutable;

/**
 * Reglas que delimitan qué contratos entran en el seguimiento de serología.
 */
class AlcanceSerologia
{
    /** Servicios contratados que llevan prueba serológica: Sangre (S) y Tejido (T). */
    public const SERVICIOS = ['S', 'T'];

    /**
     * Valor de LAB_COLECTAS.SEROLOGIA cuando la clienta ya pasó su prueba.
     * Aquí 'S' significa "Sí"; no tiene relación con el servicio Sangre.
     */
    public const SEROLOGIA_RECIBIDA = 'S';

    /** Solo se siguen los contratos firmados desde esta fecha (Y-m-d). */
    public const FECHA_INICIO = '2026-01-01';

    /** Zona horaria en la que se cuentan los días del negocio. */
    public const ZONA_HORARIA = 'America/Lima';

    /**
     * El inicio del seguimiento, a medianoche en la hora de Lima.
     */
    public static function fechaInicio(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', self::FECHA_INICIO, self::ZONA_HORARIA);
    }
}
