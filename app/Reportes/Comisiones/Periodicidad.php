<?php

namespace App\Reportes\Comisiones;

/**
 * Cada cuánto se mide una meta (COM_META.PERIODICIDAD).
 */
enum Periodicidad: string
{
    case Mensual = 'M';
    case Trimestral = 'T';
}
