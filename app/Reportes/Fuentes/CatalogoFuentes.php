<?php

namespace App\Reportes\Fuentes;

/**
 * Las fuentes que el sistema sabe cargar. Para sumar una fuente nueva se
 * agrega aquí, se crea su tabla y su fila en FUENTES_DATOS.
 */
class CatalogoFuentes
{
    /**
     * @return list<Fuente>
     */
    public function todas(): array
    {
        return [
            new FuenteFacturasOracle,
        ];
    }

    /**
     * Busca una fuente por su código sin distinguir mayúsculas, porque la URL
     * lo lleva en minúsculas (facturas_oracle).
     */
    public function buscar(string $codigo): ?Fuente
    {
        foreach ($this->todas() as $fuente) {
            if (strcasecmp($fuente->codigo(), $codigo) === 0) {
                return $fuente;
            }
        }

        return null;
    }
}
