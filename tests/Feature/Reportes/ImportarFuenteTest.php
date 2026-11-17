<?php

namespace Tests\Feature\Reportes;

use App\Models\FuenteDatos;
use App\Reportes\Fuentes\ColumnaFuente;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use App\Reportes\ImportarFuente;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ImportarFuenteTest extends TestCase
{
    private FuenteFacturasOracle $fuente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararFuentes();
        $this->fuente = new FuenteFacturasOracle;
    }

    /**
     * @return list<string>
     */
    private function encabezados(): array
    {
        return array_map(fn (ColumnaFuente $columna): string => $columna->encabezado, $this->fuente->columnas());
    }

    /**
     * Una fila con las 46 columnas en orden: vacías salvo las indicadas.
     *
     * @param  array<string, mixed>  $valores  encabezado => valor
     * @return list<mixed>
     */
    private function fila(array $valores): array
    {
        return array_map(fn (string $encabezado): mixed => $valores[$encabezado] ?? null, $this->encabezados());
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function xlsx(array $filas): string
    {
        return $this->crearXlsx([$this->encabezados(), ...array_map($this->fila(...), $filas)]);
    }

    private function importar(string $ruta, string $archivo = 'facturas.xlsx'): int
    {
        return app(ImportarFuente::class)->importar($this->fuente, $ruta, 'Luis Lazo', $archivo);
    }

    private function mensajeDeError(string $ruta): string
    {
        try {
            $this->importar($ruta);
        } catch (ValidationException $excepcion) {
            return $excepcion->errors()['archivo'][0];
        }

        $this->fail('Se esperaba una ValidationException.');
    }

    private function cargaAnterior(): void
    {
        DB::table('fuente_facturas_oracle')->insert(['id_interno' => 1, 'cliente' => 'CARGA ANTERIOR']);
        DB::table('fuentes_datos')->update(['fecha_actualizacion' => '2026-11-01 09:00:00', 'filas' => 1, 'archivo' => 'anterior.xlsx']);
    }

    public function test_guarda_todas_las_filas_con_sus_tipos_convertidos(): void
    {
        $ruta = $this->xlsx([
            [
                'ID interno' => 113688,
                'N° documento' => 'F040-00000078',
                'Cliente' => '  RIMAC SEGUROS  ',
                'Fecha de emisión' => new DateTimeImmutable('2025-07-24'),
                'Importe' => 1875,
                'Tipo de cambio' => 3.4799000000000002,
                'PER- Consecutivo' => '00000078',
                'Fecha de creación' => new DateTimeImmutable('2026-04-15 12:12:00'),
                'Aplicación de transacción' => ' ',
                'CONTADOR' => 1,
            ],
            [
                'ID interno' => 115953,
                'Fecha de emisión' => 45988,
                'Importe' => -941.95,
                'CONTADOR' => -1,
            ],
        ]);

        $this->assertSame(2, $this->importar($ruta));

        $this->assertDatabaseCount('fuente_facturas_oracle', 2);
        $this->assertDatabaseHas('fuente_facturas_oracle', [
            'id_interno' => 113688,
            'numero_documento' => 'F040-00000078',
            'cliente' => 'RIMAC SEGUROS',
            'fecha_emision' => '2025-07-24 00:00:00',
            'consecutivo' => '00000078',
            'fecha_creacion' => '2026-04-15 12:12:00',
            'aplicacion_transaccion' => null,
            'contador' => 1,
        ]);

        $notaDeCredito = DB::table('fuente_facturas_oracle')->where('id_interno', 115953)->first();

        $this->assertSame('2025-11-27 00:00:00', $notaDeCredito->fecha_emision);
        $this->assertEquals(-941.95, $notaDeCredito->importe);
        $this->assertEquals(-1, $notaDeCredito->contador);
        $this->assertEquals(3.4799, DB::table('fuente_facturas_oracle')->where('id_interno', 113688)->value('tipo_cambio'));
    }

    public function test_registra_la_carga_en_la_maestra(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 15:32:00', 'UTC'));

        $this->importar($this->xlsx([['ID interno' => 1], ['ID interno' => 2]]), 'facturas_noviembre.xlsx');

        $fuente = FuenteDatos::porCodigo(FuenteFacturasOracle::CODIGO);

        $this->assertSame('2026-11-17 10:32', $fuente->fecha_actualizacion->format('Y-m-d H:i'));
        $this->assertSame('Luis Lazo', $fuente->actualizado_por);
        $this->assertSame(2, $fuente->filas);
        $this->assertSame('facturas_noviembre.xlsx', $fuente->archivo);
    }

    public function test_reemplaza_la_carga_anterior(): void
    {
        $this->cargaAnterior();

        $this->importar($this->xlsx([['ID interno' => 2, 'Cliente' => 'NUEVA']]));

        $this->assertDatabaseMissing('fuente_facturas_oracle', ['id_interno' => 1]);
        $this->assertDatabaseHas('fuente_facturas_oracle', ['id_interno' => 2, 'cliente' => 'NUEVA']);
    }

    public function test_una_fila_invalida_no_guarda_nada_y_conserva_la_carga_anterior(): void
    {
        $this->cargaAnterior();

        $ruta = $this->xlsx([
            ['ID interno' => 2],
            ['ID interno' => 3, 'Importe' => '1,875.00'],
        ]);

        $this->assertSame(
            'No se guardó ninguna fila. Fila 3: Importe: «1,875.00» no es un número válido; usa punto decimal y sin separador de miles.',
            $this->mensajeDeError($ruta),
        );

        $this->assertDatabaseCount('fuente_facturas_oracle', 1);
        $this->assertDatabaseHas('fuente_facturas_oracle', ['id_interno' => 1, 'cliente' => 'CARGA ANTERIOR']);
        $this->assertDatabaseHas('fuentes_datos', ['fecha_actualizacion' => '2026-11-01 09:00:00', 'archivo' => 'anterior.xlsx']);
    }

    public function test_muestra_hasta_5_errores_y_cuenta_los_demas(): void
    {
        $filas = array_map(fn (int $indice): array => ['ID interno' => "X{$indice}"], range(1, 7));

        $mensaje = $this->mensajeDeError($this->xlsx($filas));

        $this->assertStringStartsWith('No se guardó ninguna fila. Fila 2: ID interno: «X1» no es un número entero. Fila 3:', $mensaje);
        $this->assertStringContainsString('Fila 6: ID interno: «X5» no es un número entero.', $mensaje);
        $this->assertStringNotContainsString('Fila 7:', $mensaje);
        $this->assertStringEndsWith('Hay 2 errores más.', $mensaje);
    }

    public function test_avisa_cuando_falta_una_columna_obligatoria_en_una_fila(): void
    {
        $mensaje = $this->mensajeDeError($this->xlsx([['Cliente' => 'SIN ID']]));

        $this->assertSame('No se guardó ninguna fila. Fila 2: ID interno: falta el valor.', $mensaje);
    }

    public function test_avisa_que_columnas_faltan_en_el_archivo(): void
    {
        $encabezados = array_values(array_diff($this->encabezados(), ['Importe', 'Moneda']));
        $ruta = $this->crearXlsx([$encabezados, array_fill(0, count($encabezados), null)]);

        $this->assertSame('Faltan columnas en el archivo: Moneda, Importe.', $this->mensajeDeError($ruta));
    }

    public function test_si_un_encabezado_se_repite_usa_la_ultima_columna(): void
    {
        $ruta = $this->crearXlsx([
            [...$this->encabezados(), 'Importe'],
            [...$this->fila(['ID interno' => 1, 'Importe' => 2212.5]), 1875],
        ]);

        $this->importar($ruta);

        $this->assertEquals(1875, DB::table('fuente_facturas_oracle')->value('importe'));
    }

    public function test_un_texto_mas_largo_que_su_columna_es_un_error(): void
    {
        $mensaje = $this->mensajeDeError($this->xlsx([['ID interno' => 1, 'PER- Serie' => 'F040-00000078']]));

        $this->assertSame('No se guardó ninguna fila. Fila 2: PER- Serie: tiene 13 caracteres; el máximo es 10.', $mensaje);
    }

    public function test_rechaza_un_archivo_sin_filas(): void
    {
        $this->cargaAnterior();

        $this->assertSame('El archivo no tiene filas para cargar.', $this->mensajeDeError($this->xlsx([])));
        $this->assertDatabaseHas('fuente_facturas_oracle', ['id_interno' => 1]);
    }

    public function test_acepta_el_mismo_id_interno_en_varias_filas(): void
    {
        $ruta = $this->xlsx([
            ['ID interno' => 113688, 'Artículo' => 'Criopreservación'],
            ['ID interno' => 113688, 'Artículo' => 'Mantenimiento anual'],
        ]);

        $this->assertSame(2, $this->importar($ruta));
        $this->assertSame(2, DB::table('fuente_facturas_oracle')->where('id_interno', 113688)->count());
    }

    public function test_acepta_menos_filas_que_la_carga_anterior(): void
    {
        $this->importar($this->xlsx([['ID interno' => 1], ['ID interno' => 2], ['ID interno' => 3]]));

        $this->assertSame(1, $this->importar($this->xlsx([['ID interno' => 4]])));
        $this->assertDatabaseCount('fuente_facturas_oracle', 1);
    }

    public function test_inserta_en_bloques_sin_perder_filas(): void
    {
        config(['reportes.filas_por_bloque' => 2]);

        $filas = array_map(fn (int $id): array => ['ID interno' => $id], range(1, 5));

        $this->assertSame(5, $this->importar($this->xlsx($filas)));
        $this->assertSame(range(1, 5), DB::table('fuente_facturas_oracle')->orderBy('id_interno')->pluck('id_interno')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_si_falla_al_registrar_la_carga_se_deshace_todo(): void
    {
        $this->cargaAnterior();
        DB::table('fuentes_datos')->delete();

        try {
            $this->importar($this->xlsx([['ID interno' => 2]]));
            $this->fail('Se esperaba una RuntimeException.');
        } catch (RuntimeException $excepcion) {
            $this->assertStringContainsString('no está registrada', $excepcion->getMessage());
        }

        $this->assertDatabaseCount('fuente_facturas_oracle', 1);
        $this->assertDatabaseHas('fuente_facturas_oracle', ['id_interno' => 1, 'cliente' => 'CARGA ANTERIOR']);
    }
}
