<?php

namespace App\Reportes\Fuentes;

/**
 * Una columna de una fuente: cómo se llama en el Excel, dónde se guarda en
 * Oracle y qué valores admite.
 *
 * Se crea con los constructores con nombre (texto(), entero(), …) para que
 * la definición de cada fuente se lea como una tabla.
 */
final readonly class ColumnaFuente
{
    /**
     * @param  string  $encabezado  texto exacto de la primera fila del Excel
     * @param  string  $columna  nombre de la columna en Oracle, en minúsculas
     * @param  int|null  $longitud  máximo de caracteres (solo Texto)
     * @param  int|null  $precision  total de dígitos (Entero y Decimal)
     * @param  int|null  $escala  dígitos decimales (solo Decimal)
     * @param  bool  $obligatoria  si cada fila debe traer un valor
     */
    public function __construct(
        public string $encabezado,
        public string $columna,
        public TipoColumna $tipo,
        public ?int $longitud = null,
        public ?int $precision = null,
        public ?int $escala = null,
        public bool $obligatoria = false,
    ) {}

    public static function texto(string $encabezado, string $columna, int $longitud, bool $obligatoria = false): self
    {
        return new self($encabezado, $columna, TipoColumna::Texto, longitud: $longitud, obligatoria: $obligatoria);
    }

    public static function entero(string $encabezado, string $columna, int $precision, bool $obligatoria = false): self
    {
        return new self($encabezado, $columna, TipoColumna::Entero, precision: $precision, escala: 0, obligatoria: $obligatoria);
    }

    public static function decimal(string $encabezado, string $columna, int $precision, int $escala, bool $obligatoria = false): self
    {
        return new self($encabezado, $columna, TipoColumna::Decimal, precision: $precision, escala: $escala, obligatoria: $obligatoria);
    }

    public static function fecha(string $encabezado, string $columna, bool $obligatoria = false): self
    {
        return new self($encabezado, $columna, TipoColumna::Fecha, obligatoria: $obligatoria);
    }

    public static function fechaHora(string $encabezado, string $columna, bool $obligatoria = false): self
    {
        return new self($encabezado, $columna, TipoColumna::FechaHora, obligatoria: $obligatoria);
    }
}
