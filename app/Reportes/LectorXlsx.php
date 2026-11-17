<?php

namespace App\Reportes;

use DateInterval;
use DateTimeInterface;
use Generator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Recorre la primera hoja de un .xlsx fila por fila, sin cargarlo entero en
 * memoria. No convierte tipos: entrega los valores tal como los lee openspout
 * (texto, número, fecha, booleano o null).
 */
class LectorXlsx
{
    /**
     * Devuelve cada fila de datos con su número de fila de Excel como clave.
     *
     * La primera fila son los encabezados. Si un encabezado se repite, gana la
     * última columna. Las columnas que no están en $encabezados se ignoran y
     * las filas vacías se saltan.
     *
     * Como es un generador, los errores (archivo ilegible, columnas faltantes)
     * se lanzan al empezar a recorrerlo, no al llamar al método.
     *
     * @param  list<string>  $encabezados  encabezados que debe traer el archivo
     * @return Generator<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function leer(string $ruta, array $encabezados): Generator
    {
        $lector = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $lector->open($ruta);

            foreach ($lector->getSheetIterator() as $hoja) {
                $indices = null;

                foreach ($hoja->getRowIterator() as $numeroFila => $fila) {
                    $valores = array_map($this->normalizarValor(...), $fila->toArray());

                    if ($indices === null) {
                        $indices = $this->indicesDeEncabezados($valores, $encabezados);

                        continue;
                    }

                    if ($this->estaVacia($valores)) {
                        continue;
                    }

                    $registro = [];

                    foreach ($indices as $encabezado => $indice) {
                        $registro[$encabezado] = $valores[$indice] ?? null;
                    }

                    yield $numeroFila => $registro;
                }

                if ($indices === null) {
                    $this->indicesDeEncabezados([], $encabezados);
                }

                break;
            }
        } catch (OpenSpoutException) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Verifica que sea un .xlsx válido (si viene de NetSuite como .xls, ábrelo y guárdalo como .xlsx).',
            ]);
        } finally {
            $lector->close();
        }
    }

    /**
     * Ubica cada encabezado esperado en la fila de encabezados del archivo.
     *
     * @param  array<int, mixed>  $fila
     * @param  list<string>  $encabezados
     * @return array<string, int> encabezado => índice de columna
     *
     * @throws ValidationException
     */
    private function indicesDeEncabezados(array $fila, array $encabezados): array
    {
        $indicePorEncabezado = [];

        foreach ($fila as $indice => $valor) {
            if (is_string($valor) || is_int($valor) || is_float($valor)) {
                $indicePorEncabezado[$this->normalizarEncabezado((string) $valor)] = $indice;
            }
        }

        $indices = [];
        $faltantes = [];

        foreach ($encabezados as $encabezado) {
            $indice = $indicePorEncabezado[$this->normalizarEncabezado($encabezado)] ?? null;

            if ($indice === null) {
                $faltantes[] = $encabezado;
            } else {
                $indices[$encabezado] = $indice;
            }
        }

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'archivo' => 'Faltan columnas en el archivo: '.implode(', ', $faltantes).'.',
            ]);
        }

        return $indices;
    }

    private function normalizarEncabezado(string $encabezado): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($encabezado));
    }

    /**
     * Convierte el texto enriquecido de openspout en texto plano; el resto de
     * valores se devuelve igual.
     */
    private function normalizarValor(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return implode('', array_map(fn (TextRun $tramo): string => $tramo->text, $valor));
        }

        return $valor;
    }

    /**
     * @param  array<int, mixed>  $valores
     */
    private function estaVacia(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($valor instanceof DateTimeInterface || $valor instanceof DateInterval || is_bool($valor) || is_int($valor) || is_float($valor)) {
                return false;
            }

            if (is_string($valor) && trim($valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
