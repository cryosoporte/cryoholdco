<?php

namespace App\Reportes;

use Illuminate\Validation\ValidationException;

class LectorCsvNetsuite
{
    /**
     * Columnas que debe traer la exportación de NetSuite.
     *
     * @var list<string>
     */
    public const COLUMNAS = ['ID interno', 'N° documento', 'Fecha de emisión', 'Importe'];

    /**
     * Lee el CSV y devuelve una fila por registro, con el nombre de la columna como clave.
     *
     * @return list<array<string, string>>
     *
     * @throws ValidationException
     */
    public function leer(string $ruta): array
    {
        $manejador = fopen($ruta, 'r');

        try {
            $primeraLinea = $this->aUtf8((string) fgets($manejador));
            $primeraLinea = preg_replace('/^\xEF\xBB\xBF/', '', $primeraLinea);
            $separador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';
            $cabeceras = array_map('trim', str_getcsv($primeraLinea, $separador, '"', ''));

            $faltantes = array_diff(self::COLUMNAS, $cabeceras);

            if ($faltantes !== []) {
                throw ValidationException::withMessages([
                    'archivo' => 'Faltan columnas en el archivo: '.implode(', ', $faltantes).'.',
                ]);
            }

            $filas = [];

            while (($valores = fgetcsv($manejador, null, $separador, '"', '')) !== false) {
                $valores = array_map(fn (?string $valor): string => trim($this->aUtf8((string) $valor)), $valores);

                if (implode('', $valores) === '') {
                    continue;
                }

                $valores = array_slice(array_pad($valores, count($cabeceras), ''), 0, count($cabeceras));
                $filas[] = array_combine($cabeceras, $valores);
            }

            return $filas;
        } finally {
            fclose($manejador);
        }
    }

    /**
     * Convierte a UTF-8 el texto guardado como "CSV" a secas desde Excel (Windows-1252).
     */
    private function aUtf8(string $texto): string
    {
        return mb_check_encoding($texto, 'UTF-8')
            ? $texto
            : mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    }
}
