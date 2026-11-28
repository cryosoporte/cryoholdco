<?php

namespace App\Informes;

/**
 * Los conteos de las tarjetas de Informes. Cada tarjeta cuenta los contratos base que mostraría el listado si
 * se pulsara, dados la búsqueda y los demás filtros activos; así los números siempre cuadran con la tabla.
 */
class ResumenInformes
{
    public function __construct(
        private readonly FilasInformes $filas,
        private readonly ClasificadorInformes $clasificador,
    ) {}

    /**
     * @return array{total: int, liberacion: array<string, int>, antiguedad: array<string, int>, sangre: array<string, int>, tejido: array<string, int>}
     */
    public function calcular(string $empresa, FiltrosInformes $filtros): array
    {
        $hoy = $this->clasificador->hoy();
        $activos = $this->clasificador->filtrosActivos($filtros);
        $resumen = [
            'total' => 0,
            'liberacion' => array_fill_keys(AlcanceInformes::LIBERACIONES, 0),
            'antiguedad' => array_fill_keys(array_keys(AlcanceInformes::ANTIGUEDADES), 0),
            'sangre' => array_fill_keys(AlcanceInformes::RESULTADOS, 0),
            'tejido' => array_fill_keys(AlcanceInformes::RESULTADOS, 0),
        ];

        foreach ($this->filas->de($empresa) as $fila) {
            if (! $this->clasificador->coincideConLaBusqueda($fila, $filtros->buscar)) {
                continue;
            }

            $valores = $this->clasificador->valores($fila, $hoy);
            $incumplen = array_keys(array_filter(
                $activos,
                fn (?string $activo, string $dimension): bool => $activo !== null && $valores[$dimension] !== $activo,
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($incumplen === []) {
                $resumen['total']++;
            }

            // Cada tarjeta cuenta ignorando el filtro de su propia dimensión.
            foreach (['liberacion', 'antiguedad', 'sangre', 'tejido'] as $dimension) {
                $valor = $valores[$dimension];

                if ($valor !== null && array_diff($incumplen, [$dimension]) === []) {
                    $resumen[$dimension][$valor]++;
                }
            }
        }

        return $resumen;
    }
}
