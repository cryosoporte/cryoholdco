<?php

namespace App\Reportes\Comisiones;

/**
 * Con qué se ubica el tramo de una meta (COM_META.BASE_TRAMO).
 */
enum BaseTramo: string
{
    /** Con la cantidad: contratos o, en morosos, años de atraso. */
    case Cantidad = 'CANTIDAD';

    /** Con el monto base en soles. */
    case Monto = 'MONTO';
}
