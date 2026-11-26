<?php

namespace App\Reportes\Comisiones;

/**
 * Cómo se convierte el valor del tramo alcanzado en comisión
 * (COM_META.TIPO_COD).
 */
enum TipoMeta: string
{
    /** Valor del tramo × cantidad de contratos (15 contratos × S/ 75). */
    case PorContrato = 'POR_CONTRATO';

    /** Valor del tramo (fracción: 0.05 = 5 %) × monto base. */
    case Porcentaje = 'PORCENTAJE';

    /** El valor del tramo, sin multiplicar. */
    case MontoFijo = 'MONTO_FIJO';
}
