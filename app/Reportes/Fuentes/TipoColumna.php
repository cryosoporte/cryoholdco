<?php

namespace App\Reportes\Fuentes;

/**
 * Tipo de dato de una columna de fuente: decide cómo se convierte el valor
 * del Excel antes de guardarlo en Oracle.
 */
enum TipoColumna
{
    /** NUMBER(p): número sin decimales. */
    case Entero;

    /** NUMBER(p,s): número con decimales, redondeado a la escala. */
    case Decimal;

    /** VARCHAR2(n): texto de hasta n caracteres. */
    case Texto;

    /** DATE sin hora (se guarda a las 00:00:00). */
    case Fecha;

    /** DATE con hora. */
    case FechaHora;
}
