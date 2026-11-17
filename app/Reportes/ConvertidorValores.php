<?php

namespace App\Reportes;

use App\Reportes\Fuentes\ColumnaFuente;
use App\Reportes\Fuentes\TipoColumna;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Convierte un valor leído del Excel al valor que se guarda en Oracle, según
 * el tipo de su columna.
 *
 * Los mensajes de error son cortos y en minúsculas porque el importador les
 * antepone la fila y el encabezado: «Fila 8: Importe: «1,875.00» no es…».
 */
class ConvertidorValores
{
    /** Día 0 del calendario de fechas de Excel (sistema 1900). */
    private const BASE_FECHAS_EXCEL = '1899-12-30';

    /**
     * @return int|float|string|null entero, decimal, texto, o fecha como 'Y-m-d H:i:s'
     *
     * @throws InvalidArgumentException
     */
    public function convertir(mixed $valor, ColumnaFuente $columna): int|float|string|null
    {
        if ($this->estaVacio($valor)) {
            if ($columna->obligatoria) {
                throw new InvalidArgumentException('falta el valor.');
            }

            return null;
        }

        return match ($columna->tipo) {
            TipoColumna::Entero => $this->entero($valor, $columna),
            TipoColumna::Decimal => $this->decimal($valor, $columna),
            TipoColumna::Texto => $this->texto($valor, $columna),
            TipoColumna::Fecha => $this->fecha($valor)->format('Y-m-d 00:00:00'),
            TipoColumna::FechaHora => $this->fecha($valor)->format('Y-m-d H:i:s'),
        };
    }

    private function estaVacio(mixed $valor): bool
    {
        return $valor === null || (is_string($valor) && trim($valor) === '');
    }

    private function entero(mixed $valor, ColumnaFuente $columna): int
    {
        $entero = match (true) {
            is_int($valor) => $valor,
            is_float($valor) && floor($valor) === $valor && abs($valor) < PHP_INT_MAX => (int) $valor,
            is_string($valor) && preg_match('/^-?\d+$/', trim($valor)) === 1 => (int) trim($valor),
            default => throw new InvalidArgumentException("{$this->mostrar($valor)} no es un número entero."),
        };

        if (strlen((string) abs($entero)) > $columna->precision) {
            throw new InvalidArgumentException("{$this->mostrar($valor)} tiene más de {$columna->precision} dígitos.");
        }

        return $entero;
    }

    private function decimal(mixed $valor, ColumnaFuente $columna): float
    {
        $decimal = match (true) {
            is_int($valor), is_float($valor) => (float) $valor,
            is_string($valor) && preg_match('/^-?\d+(\.\d+)?$/', trim($valor)) === 1 => (float) trim($valor),
            default => throw new InvalidArgumentException("{$this->mostrar($valor)} no es un número válido; usa punto decimal y sin separador de miles."),
        };

        $redondeado = round($decimal, $columna->escala);
        $digitosEnteros = $columna->precision - $columna->escala;

        if (abs($redondeado) >= 10 ** $digitosEnteros) {
            throw new InvalidArgumentException("{$this->mostrar($valor)} es demasiado grande (máximo {$digitosEnteros} dígitos enteros).");
        }

        return $redondeado;
    }

    private function texto(mixed $valor, ColumnaFuente $columna): string
    {
        $texto = match (true) {
            is_string($valor) => trim($valor),
            is_int($valor) => (string) $valor,
            is_float($valor) => $this->numeroComoTexto($valor),
            is_bool($valor) => $valor ? 'Sí' : 'No',
            $valor instanceof DateTimeInterface => $valor->format($valor->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i'),
            default => throw new InvalidArgumentException('no es un texto válido.'),
        };

        if (mb_strlen($texto) > $columna->longitud) {
            throw new InvalidArgumentException('tiene '.mb_strlen($texto)." caracteres; el máximo es {$columna->longitud}.");
        }

        return $texto;
    }

    /**
     * Escribe un número sin notación científica ni ceros decimales de más:
     * 98.0 → «98», 3.48 → «3.48».
     */
    private function numeroComoTexto(float $numero): string
    {
        return rtrim(rtrim(number_format($numero, 10, '.', ''), '0'), '.');
    }

    /**
     * Acepta una fecha de Excel (DateTime), un número de serie de Excel o un
     * texto «d/m/Y», «d/m/Y H:i» o «d/m/Y H:i:s».
     */
    private function fecha(mixed $valor): DateTimeInterface
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor;
        }

        if ((is_int($valor) || is_float($valor)) && $valor > 0) {
            return $this->fechaDesdeSerieExcel((float) $valor);
        }

        if (is_string($valor)) {
            $fecha = $this->fechaDesdeTexto(trim($valor));

            if ($fecha !== null) {
                return $fecha;
            }
        }

        throw new InvalidArgumentException("{$this->mostrar($valor)} no es una fecha válida; usa el formato día/mes/año.");
    }

    private function fechaDesdeSerieExcel(float $serie): DateTimeImmutable
    {
        $segundos = (int) round($serie * 86400);

        return (new DateTimeImmutable(self::BASE_FECHAS_EXCEL.' 00:00:00'))->modify("+{$segundos} seconds");
    }

    private function fechaDesdeTexto(string $texto): ?DateTimeImmutable
    {
        $formatos = [
            '#^\d{1,2}/\d{1,2}/\d{4}$#' => '!d/m/Y',
            '#^\d{1,2}/\d{1,2}/\d{4} \d{1,2}:\d{2}$#' => '!d/m/Y H:i',
            '#^\d{1,2}/\d{1,2}/\d{4} \d{1,2}:\d{2}:\d{2}$#' => '!d/m/Y H:i:s',
        ];

        foreach ($formatos as $patron => $formato) {
            if (preg_match($patron, $texto) !== 1) {
                continue;
            }

            $fecha = DateTimeImmutable::createFromFormat($formato, $texto);
            $avisos = DateTimeImmutable::getLastErrors();

            if ($fecha === false || ($avisos !== false && $avisos['warning_count'] > 0)) {
                return null;
            }

            return $fecha;
        }

        return null;
    }

    /**
     * El valor entre comillas angulares para los mensajes de error.
     */
    private function mostrar(mixed $valor): string
    {
        return '«'.match (true) {
            is_string($valor) => trim($valor),
            is_float($valor) => $this->numeroComoTexto($valor),
            is_scalar($valor) => (string) $valor,
            default => get_debug_type($valor),
        }.'»';
    }
}
