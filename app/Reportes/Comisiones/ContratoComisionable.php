<?php

namespace App\Reportes\Comisiones;

/**
 * Un contrato que cuenta para comisiones en una línea de servicio. Agrupa sus
 * facturas del período: un contrato con UCU Sangre y UCU Tejido es un solo
 * contrato UCU, con el subtotal de ambas.
 */
final readonly class ContratoComisionable
{
    public function __construct(
        public string $linea,
        public string $contrato,
        public string $fechaEmision,
        public float $monto,
        public ?string $representante,
        public ?string $captador,
        public ?string $dueno,
        public int $filas,
    ) {}

    /**
     * Nombre de la persona a la que se le cuenta el contrato, tal como viene
     * de NetSuite, o null si la atribución no es a una persona (equipo,
     * empresa, clínica).
     */
    public function asesor(Atribucion $atribucion): ?string
    {
        return match ($atribucion) {
            Atribucion::Representante => $this->representante,
            Atribucion::Captador => $this->captador,
            Atribucion::Dueno => $this->dueno,
            default => null,
        };
    }
}
