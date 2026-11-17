<?php

namespace Tests\Feature\Reportes;

use App\Reportes\LectorXlsx;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LectorXlsxTest extends TestCase
{
    /**
     * @param  list<string>  $encabezados
     * @return array<int, array<string, mixed>>
     */
    private function leer(string $ruta, array $encabezados): array
    {
        return iterator_to_array((new LectorXlsx)->leer($ruta, $encabezados));
    }

    private function mensajeDeError(callable $accion): string
    {
        try {
            $accion();
        } catch (ValidationException $excepcion) {
            return $excepcion->errors()['archivo'][0];
        }

        $this->fail('Se esperaba una ValidationException.');
    }

    public function test_devuelve_las_filas_con_su_numero_de_fila_de_excel(): void
    {
        $ruta = $this->crearXlsx([
            ['ID interno', 'Extra', 'Cliente'],
            [113688, 'se ignora', 'RIMAC SEGUROS'],
            [null, null, null],
            [115953, 'se ignora', 'PACIFICO'],
        ]);

        $filas = $this->leer($ruta, ['ID interno', 'Cliente']);

        $this->assertSame([
            2 => ['ID interno' => 113688, 'Cliente' => 'RIMAC SEGUROS'],
            4 => ['ID interno' => 115953, 'Cliente' => 'PACIFICO'],
        ], $filas);
    }

    public function test_entrega_los_valores_con_el_tipo_que_tienen_en_excel(): void
    {
        $ruta = $this->crearXlsx([
            ['Entero', 'Decimal', 'Texto', 'Fecha'],
            [98, -941.95, '00000078', new DateTimeImmutable('2025-07-24 10:30:00')],
        ]);

        $fila = $this->leer($ruta, ['Entero', 'Decimal', 'Texto', 'Fecha'])[2];

        $this->assertSame(98, $fila['Entero']);
        $this->assertSame(-941.95, $fila['Decimal']);
        $this->assertSame('00000078', $fila['Texto']);
        $this->assertInstanceOf(DateTimeInterface::class, $fila['Fecha']);
        $this->assertSame('2025-07-24 10:30', $fila['Fecha']->format('Y-m-d H:i'));
    }

    public function test_si_un_encabezado_se_repite_usa_la_ultima_columna(): void
    {
        $ruta = $this->crearXlsx([
            ['Importe', 'Cliente', 'Importe'],
            [1, 'RIMAC', 2],
        ]);

        $this->assertSame(2, $this->leer($ruta, ['Importe'])[2]['Importe']);
    }

    public function test_reconoce_encabezados_con_espacios_de_mas(): void
    {
        $ruta = $this->crearXlsx([
            ['  ID   interno '],
            [113688],
        ]);

        $this->assertSame([2 => ['ID interno' => 113688]], $this->leer($ruta, ['ID interno']));
    }

    public function test_avisa_que_columnas_faltan(): void
    {
        $ruta = $this->crearXlsx([
            ['ID interno', 'Cliente'],
            [113688, 'RIMAC'],
        ]);

        $mensaje = $this->mensajeDeError(fn () => $this->leer($ruta, ['ID interno', 'Fecha de emisión', 'Importe']));

        $this->assertSame('Faltan columnas en el archivo: Fecha de emisión, Importe.', $mensaje);
    }

    public function test_una_hoja_vacia_no_tiene_ninguna_columna(): void
    {
        $ruta = $this->crearXlsx([]);

        $mensaje = $this->mensajeDeError(fn () => $this->leer($ruta, ['ID interno']));

        $this->assertSame('Faltan columnas en el archivo: ID interno.', $mensaje);
    }

    public function test_un_archivo_que_no_es_xlsx_da_un_error_claro(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, "ID interno;Cliente\n1;RIMAC\n");

        try {
            $mensaje = $this->mensajeDeError(fn () => $this->leer($ruta, ['ID interno']));
        } finally {
            unlink($ruta);
        }

        $this->assertStringStartsWith('No se pudo leer el archivo.', $mensaje);
    }
}
