<?php

namespace App\Reportes\Fuentes;

/**
 * Una fuente con columnas que no vienen en el Excel: se calculan desde las
 * demás al cargar cada fila y se guardan junto a ellas.
 */
interface FuenteConColumnasCalculadas extends Fuente
{
    /**
     * Columnas calculadas, en el orden de la tabla. Su encabezado solo
     * describe de dónde salen: no se busca en el Excel.
     *
     * @return list<ColumnaFuente>
     */
    public function columnasCalculadas(): array;

    /**
     * Valores de las columnas calculadas para una fila ya convertida. Nunca
     * rechaza la fila: si no se puede calcular, el valor queda en null.
     *
     * @param  array<string, int|float|string|null>  $registro  columna de Oracle => valor convertido
     * @return array<string, string|null>
     */
    public function calcular(array $registro): array;
}
