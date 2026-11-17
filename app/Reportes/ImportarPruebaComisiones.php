<?php

namespace App\Reportes;

use App\Models\PruebaComision;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportarPruebaComisiones
{
    /** Cuántos errores de fila se muestran como máximo. */
    private const MAXIMO_ERRORES = 5;

    /** Filas por cada INSERT, para no hacer un viaje a Oracle por fila. */
    private const FILAS_POR_BLOQUE = 500;

    /**
     * Reemplaza el contenido de PRUEBA_COMISIONES por las filas del archivo.
     *
     * @param  list<array<string, string>>  $filas
     *
     * @throws ValidationException
     */
    public function importar(array $filas): int
    {
        $registros = $this->convertir($filas);

        DB::transaction(function () use ($registros): void {
            PruebaComision::query()->delete();

            foreach (array_chunk($registros, self::FILAS_POR_BLOQUE) as $bloque) {
                PruebaComision::query()->insert($bloque);
            }
        });

        return count($registros);
    }

    /**
     * Convierte todas las filas; si alguna falla, no devuelve ninguna.
     *
     * @param  list<array<string, string>>  $filas
     * @return list<array{id_interno: int, numero_documento: string|null, fecha_emision: string|null, importe: float|null}>
     *
     * @throws ValidationException
     */
    private function convertir(array $filas): array
    {
        $registros = [];
        $errores = [];
        $filaPorIdInterno = [];

        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 2;

            try {
                $registro = $this->convertirFila($fila);
            } catch (InvalidArgumentException $error) {
                $errores[] = "Fila {$numeroFila}: {$error->getMessage()}";

                continue;
            }

            $idInterno = $registro['id_interno'];

            if (isset($filaPorIdInterno[$idInterno])) {
                $errores[] = "Fila {$numeroFila}: el ID interno {$idInterno} ya aparece en la fila {$filaPorIdInterno[$idInterno]}.";

                continue;
            }

            $filaPorIdInterno[$idInterno] = $numeroFila;
            $registros[] = $registro;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => $this->resumirErrores($errores)]);
        }

        if ($registros === []) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no tiene filas para cargar.']);
        }

        return $registros;
    }

    /**
     * @param  array<string, string>  $fila
     * @return array{id_interno: int, numero_documento: string|null, fecha_emision: string|null, importe: float|null}
     *
     * @throws InvalidArgumentException
     */
    private function convertirFila(array $fila): array
    {
        return [
            'id_interno' => $this->idInterno($fila['ID interno']),
            'numero_documento' => $this->numeroDocumento($fila['N° documento']),
            'fecha_emision' => $this->fecha($fila['Fecha de emisión']),
            'importe' => $this->importe($fila['Importe']),
        ];
    }

    private function idInterno(string $valor): int
    {
        if ($valor === '') {
            throw new InvalidArgumentException('falta el ID interno.');
        }

        if (preg_match('/^\d{1,10}$/', $valor) !== 1) {
            throw new InvalidArgumentException("el ID interno «{$valor}» no es un número entero.");
        }

        return (int) $valor;
    }

    private function numeroDocumento(string $valor): ?string
    {
        if (mb_strlen($valor) > 20) {
            throw new InvalidArgumentException("el N° documento «{$valor}» tiene más de 20 caracteres.");
        }

        return $valor === '' ? null : $valor;
    }

    /**
     * Convierte «24/07/2025» o «6/08/2025» al formato que espera Oracle.
     */
    private function fecha(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }

        $fecha = preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $valor) === 1
            ? DateTimeImmutable::createFromFormat('!d/m/Y', $valor)
            : false;
        $avisos = DateTimeImmutable::getLastErrors();

        if ($fecha === false || ($avisos !== false && $avisos['warning_count'] > 0)) {
            throw new InvalidArgumentException("la fecha «{$valor}» no es válida; usa el formato día/mes/año.");
        }

        return $fecha->format('Y-m-d H:i:s');
    }

    private function importe(string $valor): ?float
    {
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,12}(\.\d+)?$/', $valor) !== 1) {
            throw new InvalidArgumentException("el importe «{$valor}» no es válido; usa punto para los decimales y sin separador de miles.");
        }

        return round((float) $valor, 2);
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
