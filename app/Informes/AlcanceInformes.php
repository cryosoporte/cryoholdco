<?php

namespace App\Informes;

/**
 * Reglas que delimitan qué contratos entran en Informes.
 */
class AlcanceInformes
{
    /** Estados de contrato (VEN_CONTRATOS.ESTADO_CONTRATO) que se listan: CON y DES. */
    public const ESTADOS_CONTRATO = ['CON', 'DES'];

    /** Servicios (VEN_CONTRATOS.SERVICIO) que se listan: S = Sangre y T = Tejido. */
    public const SERVICIOS = ['S', 'T'];

    /** Valores del filtro de envío: ya se enviaron sus documentos o todavía no. */
    public const ENVIOS = ['enviado', 'pendiente'];

    /** LAB_COLECTAS.FLAG_ENVIO_CORREO cuando los documentos ya se enviaron al cliente. */
    public const FLAG_ENVIADO = 1;

    /**
     * Rangos de antigüedad de la colecta de los contratos NO ENVIADOS, en días desde FECHA_COLECTA hasta hoy:
     * clave => [desde, hasta] (hasta null = sin límite).
     *
     * @var array<string, array{int, ?int}>
     */
    public const ANTIGUEDADES = [
        '0_30' => [0, 30],
        '31_60' => [31, 60],
        '61_90' => [61, 90],
        '91_mas' => [91, null],
    ];

    /** Resultados de Sangre y de Tejido por los que se puede filtrar (no_liberado: aún sin registro en LAB_COLECTAS_INFO). */
    public const RESULTADOS = ['apto', 'no_apto', 'no_liberado'];

    /**
     * Filtro de liberación: «liberado» son los contratos con todos sus servicios registrados en LAB_COLECTAS_INFO;
     * «no_liberado», los que tienen algún servicio sin registrar.
     */
    public const LIBERACIONES = ['liberado', 'no_liberado'];

    /** Valor de «envio» para ver enviados y no enviados; sin «envio» se muestran los pendientes de envío. */
    public const ENVIO_TODOS = 'todos';

    /**
     * Motivos entre los que se elige cuando un servicio es NO APTO: clave => texto que se envía al cliente.
     *
     * @var array<string, string>
     */
    public const MOTIVOS_NO_APTO = [
        'baja_viabilidad' => 'No apto por baja viabilidad',
        'cultivo_positivo' => 'No apto por cultivo positivo',
        'baja_celularidad' => 'No apto por baja celularidad',
    ];

    /** Máximo de contratos que se envían en una sola petición del envío masivo. */
    public const MAXIMO_POR_ENVIO_MASIVO = 10;
}
