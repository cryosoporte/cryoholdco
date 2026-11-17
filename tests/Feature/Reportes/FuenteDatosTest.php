<?php

namespace Tests\Feature\Reportes;

use App\Models\FuenteDatos;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FuenteDatosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararFuentes();
    }

    public function test_busca_la_fuente_por_codigo_sin_distinguir_mayusculas(): void
    {
        $fuente = FuenteDatos::porCodigo('facturas_oracle');

        $this->assertSame(FuenteFacturasOracle::CODIGO, $fuente->codigo);
        $this->assertSame('Facturas Oracle NetSuite', $fuente->nombre);
        $this->assertNull(FuenteDatos::porCodigo('no_existe'));
    }

    public function test_una_fuente_nunca_cargada_no_tiene_fecha(): void
    {
        $fuente = FuenteDatos::porCodigo(FuenteFacturasOracle::CODIGO);

        $this->assertNull($fuente->fecha_actualizacion);
        $this->assertNull($fuente->actualizado_por);
        $this->assertNull($fuente->filas);
    }

    public function test_registra_la_carga_con_la_hora_de_lima(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 15:32:00', 'UTC'));

        FuenteDatos::registrarCarga('facturas_oracle', 'Luis Lazo', 13245, 'facturas.xlsx');

        $this->assertDatabaseHas('fuentes_datos', [
            'codigo' => 'FACTURAS_ORACLE',
            'fecha_actualizacion' => '2026-11-17 10:32:00',
            'actualizado_por' => 'Luis Lazo',
            'filas' => 13245,
            'archivo' => 'facturas.xlsx',
        ]);

        $fuente = FuenteDatos::porCodigo(FuenteFacturasOracle::CODIGO);

        $this->assertSame('2026-11-17T10:32:00-05:00', $fuente->fecha_actualizacion->toIso8601String());
        $this->assertSame(13245, $fuente->filas);
    }

    public function test_registrar_una_fuente_sin_fila_en_la_maestra_es_un_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La fuente OTRA_FUENTE no está registrada en fuentes_datos.');

        FuenteDatos::registrarCarga('OTRA_FUENTE', 'Luis Lazo', 1, 'otra.xlsx');
    }

    public function test_la_tabla_lleva_el_prefijo_configurado(): void
    {
        $this->assertSame('fuentes_datos', (new FuenteDatos)->getTable());

        config(['reportes.prefijo_tablas' => 'PRUEBA_']);

        $this->assertSame('prueba_fuentes_datos', (new FuenteDatos)->getTable());
    }

    public function test_la_tabla_de_la_fuente_de_prueba_tiene_sus_46_columnas(): void
    {
        $this->assertCount(46, DB::getSchemaBuilder()->getColumnListing((new FuenteFacturasOracle)->tabla()));
    }
}
