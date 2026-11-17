<?php

namespace Tests\Unit\Reportes;

use App\Reportes\ConvertidorValores;
use App\Reportes\Fuentes\ColumnaFuente;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConvertidorValoresTest extends TestCase
{
    /**
     * @return array<string, array{ColumnaFuente, mixed, int|float|string|null}>
     */
    public static function valoresValidos(): array
    {
        $entero = ColumnaFuente::entero('ID interno', 'id_interno', 10);
        $decimal = ColumnaFuente::decimal('Importe', 'importe', 14, 2);
        $tipoCambio = ColumnaFuente::decimal('Tipo de cambio', 'tipo_cambio', 12, 6);
        $texto = ColumnaFuente::texto('PER- Consecutivo', 'consecutivo', 20);
        $fecha = ColumnaFuente::fecha('Fecha de emisión', 'fecha_emision');
        $fechaHora = ColumnaFuente::fechaHora('Fecha de creación', 'fecha_creacion');

        return [
            'vacío es null' => [$texto, null, null],
            'texto vacío es null' => [$texto, '', null],
            'solo espacios es null' => [$texto, '   ', null],
            'entero' => [$entero, 113688, 113688],
            'entero negativo' => [$entero, -1, -1],
            'entero escrito como decimal sin fracción' => [$entero, 113688.0, 113688],
            'entero como texto' => [$entero, ' 113688 ', 113688],
            'decimal' => [$decimal, -941.95, -941.95],
            'decimal desde entero' => [$decimal, 1875, 1875.0],
            'decimal como texto con punto' => [$decimal, '2318.64', 2318.64],
            'decimal redondeado a la escala' => [$decimal, 521.70000000000005, 521.7],
            'tipo de cambio con ruido de coma flotante' => [$tipoCambio, 3.4799000000000002, 3.4799],
            'texto con espacios alrededor' => [$texto, '  F040  ', 'F040'],
            'texto que conserva ceros' => [$texto, '00000078', '00000078'],
            'número entero como texto' => [$texto, 98, '98'],
            'número decimal como texto' => [$texto, 98.0, '98'],
            'fecha de Excel' => [$fecha, new DateTimeImmutable('2025-07-24 00:00:00'), '2025-07-24 00:00:00'],
            'fecha de Excel pierde la hora' => [$fecha, new DateTimeImmutable('2025-07-24 10:30:00'), '2025-07-24 00:00:00'],
            'fecha como número de serie' => [$fecha, 45862, '2025-07-24 00:00:00'],
            'fecha como texto' => [$fecha, '6/08/2025', '2025-08-06 00:00:00'],
            'fecha y hora de Excel' => [$fechaHora, new DateTimeImmutable('2026-04-15 12:12:00'), '2026-04-15 12:12:00'],
            'fecha y hora como número de serie' => [$fechaHora, 46127.508333333331, '2026-04-15 12:12:00'],
            'fecha y hora como texto' => [$fechaHora, '15/04/2026 12:12', '2026-04-15 12:12:00'],
        ];
    }

    #[DataProvider('valoresValidos')]
    public function test_convierte_valores_validos(ColumnaFuente $columna, mixed $valor, int|float|string|null $esperado): void
    {
        $this->assertSame($esperado, (new ConvertidorValores)->convertir($valor, $columna));
    }

    /**
     * @return array<string, array{ColumnaFuente, mixed, string}>
     */
    public static function valoresInvalidos(): array
    {
        $entero = ColumnaFuente::entero('ID interno', 'id_interno', 10, obligatoria: true);
        $contador = ColumnaFuente::entero('CONTADOR', 'contador', 2);
        $decimal = ColumnaFuente::decimal('Importe', 'importe', 14, 2);
        $serie = ColumnaFuente::texto('PER- Serie', 'serie', 10);
        $fecha = ColumnaFuente::fecha('Fecha de emisión', 'fecha_emision');

        return [
            'falta un valor obligatorio' => [$entero, '', 'falta el valor.'],
            'entero con letras' => [$entero, 'abc', '«abc» no es un número entero.'],
            'entero con decimales' => [$entero, 12.5, '«12.5» no es un número entero.'],
            'entero con demasiados dígitos' => [$contador, 100, '«100» tiene más de 2 dígitos.'],
            'decimal con separador de miles' => [$decimal, '1,875.00', '«1,875.00» no es un número válido; usa punto decimal y sin separador de miles.'],
            'decimal con coma decimal' => [$decimal, '1875,50', '«1875,50» no es un número válido; usa punto decimal y sin separador de miles.'],
            'decimal demasiado grande' => [$decimal, 1e12, '«1000000000000» es demasiado grande (máximo 12 dígitos enteros).'],
            'texto demasiado largo' => [$serie, 'F040-00000078', 'tiene 13 caracteres; el máximo es 10.'],
            'fecha inexistente' => [$fecha, '31/02/2025', '«31/02/2025» no es una fecha válida; usa el formato día/mes/año.'],
            'fecha en otro formato' => [$fecha, '2025-07-24', '«2025-07-24» no es una fecha válida; usa el formato día/mes/año.'],
            'fecha con año de dos dígitos' => [$fecha, '24/07/25', '«24/07/25» no es una fecha válida; usa el formato día/mes/año.'],
            'fecha que es texto' => [$fecha, 'pendiente', '«pendiente» no es una fecha válida; usa el formato día/mes/año.'],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_rechaza_valores_invalidos_con_un_mensaje_claro(ColumnaFuente $columna, mixed $valor, string $mensaje): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($mensaje);

        (new ConvertidorValores)->convertir($valor, $columna);
    }

    public function test_cuenta_los_caracteres_con_tilde_como_uno(): void
    {
        $columna = ColumnaFuente::texto('Subsidiaria', 'subsidiaria', 13);

        $this->assertSame('CRIOPRESERVACIÓN', (new ConvertidorValores)->convertir('CRIOPRESERVACIÓN', ColumnaFuente::texto('Subsidiaria', 'subsidiaria', 16)));

        $this->expectException(InvalidArgumentException::class);

        (new ConvertidorValores)->convertir('CRIOPRESERVACIÓN', $columna);
    }
}
