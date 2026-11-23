<?php

namespace App\Reportes\Comisiones;

/**
 * Las anualidades de un contrato y servicio (Sangre o Tejido) cobradas en el
 * período que cuentan como morosas: al menos 3 anualidades pagadas y la más
 * antigua empieza 2 o más años antes del año actual.
 */
final readonly class AnualidadMorosa
{
    /**
     * @param  int  $anios  anualidades pagadas en el período (cada una es un año)
     * @param  float  $monto  suma de la columna Importe
     */
    public function __construct(
        public string $contrato,
        public string $servicio,
        public ?string $dueno,
        public int $anios,
        public int $anioInicioMasAntiguo,
        public float $monto,
        public string $fechaEmision,
    ) {}
}
