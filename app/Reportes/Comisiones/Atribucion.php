<?php

namespace App\Reportes\Comisiones;

/**
 * A quién se le cuenta una factura para una meta (COM_META.ATRIBUCION).
 */
enum Atribucion: string
{
    case Representante = 'REPRESENTANTE';
    case Captador = 'CAPTADOR';
    case Dueno = 'DUENO';
    case Equipo = 'EQUIPO';
    case Empresa = 'EMPRESA';
    case Clinica = 'CLINICA';

    /**
     * Si la factura se le cuenta a la persona que nombra una columna de la
     * fuente (representante, captador o dueño).
     */
    public function esPersonal(): bool
    {
        return in_array($this, [self::Representante, self::Captador, self::Dueno], true);
    }
}
