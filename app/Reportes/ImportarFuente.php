<?php

namespace App\Reportes;

use App\Models\FuenteDatos;
use App\Reportes\Fuentes\ColumnaFuente;
use App\Reportes\Fuentes\Fuente;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Reemplaza el contenido de una fuente con las filas de un .xlsx.
 *
 * Lee el archivo dos veces para no tener todas las filas en memoria:
 * 1. valida todas las filas sin guardar nada; si alguna falla, no toca la tabla;
 * 2. dentro de una transacción borra la carga anterior, inserta en bloques y
 *    registra la carga en la maestra. Si algo falla, Oracle deshace todo y la
 *    carga anterior queda intacta.
 */
class ImportarFuente
{
    /** Cuántos errores de fila se muestran como máximo. */
    private const MAXIMO_ERRORES = 5;

    public function __construct(
        private LectorXlsx $lector,
        private ConvertidorValores $convertidor,
    ) {}

    /**
     * @param  string  $usuario  quién carga (se guarda en la maestra)
     * @param  string  $archivo  nombre original del archivo (se guarda en la maestra)
     * @return int filas guardadas
     *
     * @throws ValidationException si el archivo no se puede leer, le faltan columnas, no tiene filas o alguna fila es inválida
     */
    public function importar(Fuente $fuente, string $ruta, string $usuario, string $archivo): int
    {
        $filas = $this->validar($fuente, $ruta);

        DB::transaction(function () use ($fuente, $ruta, $usuario, $archivo, $filas): void {
            DB::table($fuente->tabla())->delete();

            $bloque = [];
            $filasPorBloque = (int) config('reportes.filas_por_bloque');

            foreach ($this->registros($fuente, $ruta) as $registro) {
                $bloque[] = $registro;

                if (count($bloque) === $filasPorBloque) {
                    DB::table($fuente->tabla())->insert($bloque);
                    $bloque = [];
                }
            }

            if ($bloque !== []) {
                DB::table($fuente->tabla())->insert($bloque);
            }

            FuenteDatos::registrarCarga($fuente->codigo(), $usuario, $filas, $archivo);
        });

        return $filas;
    }

    /**
     * Primera pasada: convierte todas las filas sin guardarlas y junta los
     * errores (el primero de cada fila).
     *
     * @return int filas válidas
     *
     * @throws ValidationException
     */
    private function validar(Fuente $fuente, string $ruta): int
    {
        $filas = 0;
        $errores = [];

        foreach ($this->lector->leer($ruta, $this->encabezados($fuente)) as $numeroFila => $fila) {
            try {
                $this->convertirFila($fuente, $fila);
                $filas++;
            } catch (InvalidArgumentException $error) {
                $errores[] = "Fila {$numeroFila}: {$error->getMessage()}";
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => $this->resumirErrores($errores)]);
        }

        if ($filas === 0) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene filas para cargar.']);
        }

        return $filas;
    }

    /**
     * Segunda pasada: las filas ya convertidas, listas para insertar.
     *
     * @return Generator<int, array<string, int|float|string|null>>
     */
    private function registros(Fuente $fuente, string $ruta): Generator
    {
        foreach ($this->lector->leer($ruta, $this->encabezados($fuente)) as $fila) {
            yield $this->convertirFila($fuente, $fila);
        }
    }

    /**
     * @param  array<string, mixed>  $fila  encabezado => valor leído del Excel
     * @return array<string, int|float|string|null> columna de Oracle => valor convertido
     *
     * @throws InvalidArgumentException con el encabezado delante del mensaje
     */
    private function convertirFila(Fuente $fuente, array $fila): array
    {
        $registro = [];

        foreach ($fuente->columnas() as $columna) {
            try {
                $registro[$columna->columna] = $this->convertidor->convertir($fila[$columna->encabezado] ?? null, $columna);
            } catch (InvalidArgumentException $error) {
                throw new InvalidArgumentException("{$columna->encabezado}: {$error->getMessage()}", previous: $error);
            }
        }

        return $registro;
    }

    /**
     * @return list<string>
     */
    private function encabezados(Fuente $fuente): array
    {
        return array_map(fn (ColumnaFuente $columna): string => $columna->encabezado, $fuente->columnas());
    }

    /**
     * @param  list<string>  $errores
     */
    private function resumirErrores(array $errores): string
    {
        $mostrados = array_slice($errores, 0, self::MAXIMO_ERRORES);
        $restantes = count($errores) - count($mostrados);

        return 'No se guardó ninguna fila. '.implode(' ', $mostrados)
            .($restantes > 0 ? " Hay {$restantes} errores más." : '');
    }
}
