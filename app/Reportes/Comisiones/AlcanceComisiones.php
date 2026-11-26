<?php

namespace App\Reportes\Comisiones;

/**
 * Reglas fijas del cálculo de comisiones que no están en las tablas COM_*.
 */
class AlcanceComisiones
{
    /**
     * Líneas que se cuentan por fila de la fuente (línea de servicio) y no por
     * contrato. Seguridad Total: un contrato con vitalicio de Sangre y de
     * Tejido son 2 ST.
     */
    public const LINEAS_POR_FILA = ['ST'];

    /**
     * Rol del jefe => rol de su equipo. En las metas con atribución EQUIPO, lo
     * logrado es la suma de lo que logró cada integrante del equipo en la
     * misma línea.
     *
     * @var array<string, string>
     */
    public const EQUIPOS = [
        'JEFE DE MARKETING' => 'EJECUTIVO DE VENTAS',
        'JEFE DE OPERACIONES' => 'EJECUTIVO DE ANUALIDADES',
    ];

    /**
     * Líneas que tienen meta pero cuyos datos todavía no están en ninguna
     * fuente cargada. Se muestran como pendientes en vez de comisión 0.
     *
     * @var list<string>
     */
    public const LINEAS_SIN_FUENTE = [];

    /**
     * Líneas que se pagan por bloques de antigüedad: cada anualidad morosa
     * cae en el tramo de sus años (3, 4, 5+) y se paga el porcentaje de ese
     * tramo sobre su monto. La comisión es la suma de los bloques.
     */
    public const LINEAS_POR_ANTIGUEDAD = ['MOROSO'];

    /**
     * Líneas cuyo monto, en las metas de empresa o clínica, es el neto de
     * todos los comprobantes del período: incluye notas de crédito y
     * comprobantes con "Aplicación de transacción". La cantidad de contratos
     * no cambia.
     */
    public const LINEAS_CON_MONTO_NETO = ['ANUALIDAD'];

    /**
     * Primer período con metas en COM_META.
     */
    public const PRIMER_PERIODO = '2026-10';

    /**
     * Cargo de la pantalla por rol de COM_META, en el orden de las pestañas:
     * rol => [código, etiqueta, título].
     *
     * @var array<string, array{string, string, string}>
     */
    public const CARGOS = [
        'EJECUTIVO DE VENTAS' => ['EJEC', 'Ejecutivo de ventas', 'Ejecutivos de venta'],
        'REPRESENTANTE DE PROM LIMA' => ['LIMA', 'Rep. Prom. Lima', 'Representantes de promoción Lima'],
        'REPRESENTANTE DE PROM PROVINCIA' => ['PROV', 'Rep. Prom. Provincia', 'Representantes de promoción Provincia'],
        'EJECUTIVO DE ANUALIDADES' => ['ANUAL', 'Ejecutivo de anualidades', 'Ejecutivos de anualidades'],
        'JEFE DE OPERACIONES' => ['OPE', 'Jefe de operaciones', 'Jefe de operaciones'],
        'JEFE DE MARKETING' => ['MKT', 'Jefe de marketing', 'Jefe de marketing'],
    ];

    /**
     * Nombre visible de cada línea.
     *
     * @var array<string, string>
     */
    public const LINEAS = [
        'UCU' => 'UCU',
        'TAMIZAJE' => 'Tamizaje',
        'ADN' => 'ADN',
        'NIPT' => 'NIPT / Genetic Loss',
        'ST' => 'Seguridad Total',
        'MOROSO' => 'Morosos',
        'ANUALIDAD' => 'Anualidades',
        BonoProntaVenta::LINEA => 'Bono pronta venta',
    ];
}
