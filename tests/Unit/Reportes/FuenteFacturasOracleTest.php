<?php

namespace Tests\Unit\Reportes;

use App\Reportes\Fuentes\CatalogoFuentes;
use App\Reportes\Fuentes\ColumnaFuente;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use App\Reportes\Fuentes\TipoColumna;
use Tests\TestCase;

class FuenteFacturasOracleTest extends TestCase
{
    public function test_define_las_46_columnas_sin_repetir_encabezados_ni_columnas(): void
    {
        $columnas = (new FuenteFacturasOracle)->columnas();

        $this->assertCount(46, $columnas);
        $this->assertCount(46, array_unique(array_map(fn (ColumnaFuente $columna): string => $columna->encabezado, $columnas)));
        $this->assertCount(46, array_unique(array_map(fn (ColumnaFuente $columna): string => $columna->columna, $columnas)));
    }

    public function test_los_nombres_de_columna_caben_en_oracle_10g(): void
    {
        foreach ((new FuenteFacturasOracle)->columnas() as $columna) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,29}$/', $columna->columna);
        }
    }

    public function test_solo_el_id_interno_es_obligatorio(): void
    {
        $obligatorias = array_values(array_filter(
            (new FuenteFacturasOracle)->columnas(),
            fn (ColumnaFuente $columna): bool => $columna->obligatoria,
        ));

        $this->assertCount(1, $obligatorias);
        $this->assertSame('id_interno', $obligatorias[0]->columna);
        $this->assertSame(TipoColumna::Entero, $obligatorias[0]->tipo);
    }

    public function test_cada_tipo_trae_los_limites_que_necesita(): void
    {
        foreach ((new FuenteFacturasOracle)->columnas() as $columna) {
            match ($columna->tipo) {
                TipoColumna::Texto => $this->assertGreaterThan(0, $columna->longitud, $columna->columna),
                TipoColumna::Entero => $this->assertSame(0, $columna->escala, $columna->columna),
                TipoColumna::Decimal => $this->assertGreaterThan($columna->escala, $columna->precision, $columna->columna),
                TipoColumna::Fecha, TipoColumna::FechaHora => $this->assertNull($columna->longitud, $columna->columna),
            };
        }
    }

    public function test_la_tabla_lleva_el_prefijo_configurado(): void
    {
        $fuente = new FuenteFacturasOracle;

        $this->assertSame('fuente_facturas_oracle', $fuente->tabla());

        config(['reportes.prefijo_tablas' => 'PRUEBA_']);

        $this->assertSame('prueba_fuente_facturas_oracle', $fuente->tabla());
    }

    public function test_el_catalogo_busca_por_codigo_sin_distinguir_mayusculas(): void
    {
        $catalogo = new CatalogoFuentes;

        $this->assertInstanceOf(FuenteFacturasOracle::class, $catalogo->buscar('facturas_oracle'));
        $this->assertInstanceOf(FuenteFacturasOracle::class, $catalogo->buscar('FACTURAS_ORACLE'));
        $this->assertNull($catalogo->buscar('no_existe'));
        $this->assertCount(1, $catalogo->todas());
    }
}
