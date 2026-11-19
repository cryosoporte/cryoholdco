<?php

namespace Tests\Unit\Reportes;

use App\Reportes\Comisiones\DatosEjemploComisiones;
use PHPUnit\Framework\TestCase;

class DatosEjemploComisionesTest extends TestCase
{
    public function test_el_historial_y_cada_desglose_muestran_los_mismos_totales(): void
    {
        $datos = new DatosEjemploComisiones;

        $resumenes = $datos->liquidaciones();

        $this->assertSame(['2026-10', '2026-09', '2026-08'], array_column($resumenes, 'id'));

        foreach ($resumenes as $resumen) {
            $liquidacion = $datos->liquidacion($resumen['id']);
            $this->assertNotNull($liquidacion);
            $this->assertSame(8187.98, $resumen['total']);
            $this->assertSame($resumen, array_intersect_key($liquidacion, $resumen));
            $this->assertSame(8187.98, round(array_sum(array_column($liquidacion['cargos'], 'total')), 2));
            $this->assertSame(7, array_sum(array_column($liquidacion['resumen_cargos'], 'con_comision')));
            $this->assertSame(13, array_sum(array_column($liquidacion['resumen_cargos'], 'colaboradores')));
        }
    }

    public function test_cada_cargo_y_asesor_concilian_sus_importes_y_destinatarios(): void
    {
        $datos = new DatosEjemploComisiones;

        $liquidacion = $datos->liquidacion('2026-10');

        $this->assertNotNull($liquidacion);
        $this->assertSame(['EJEC', 'LIMA', 'PROV', 'ANUAL', 'OPE', 'MKT'], array_column($liquidacion['cargos'], 'codigo'));
        $asesores = array_column($liquidacion['asesores'], null, 'id');
        $this->assertCount(13, $asesores);
        $this->assertCount(13, array_unique(array_column($asesores, 'correo')));

        foreach ($liquidacion['cargos'] as $cargo) {
            $this->assertSame($cargo['total'], round(array_sum(array_column($cargo['filas'], 'comision')), 2));

            foreach ($cargo['filas'] as $fila) {
                $this->assertArrayHasKey($fila['asesor_id'], $asesores);
                $asesor = $asesores[$fila['asesor_id']];
                $this->assertSame($cargo['codigo'], $asesor['cargo_codigo']);
                $this->assertSame($fila['nombre'], $asesor['nombre']);
                $this->assertSame($fila['comision'], $asesor['total']);
                $this->assertSame($asesor['total'], round(array_sum(array_column($asesor['lineas'], 'comision')), 2));
                $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z]+@ejemplo\.test$/', $asesor['correo']);
                $this->assertCount(count($cargo['columnas']), $fila['celdas']);
            }
        }

        $this->assertSame('gerencia@ejemplo.test', $liquidacion['correo_gerencia']);
    }

    public function test_ofrece_periodos_validos_y_respeta_el_rango_del_cierre_trimestral(): void
    {
        $datos = new DatosEjemploComisiones;

        $liquidacion = $datos->liquidacion('2026-T4', '2026-T4');

        $this->assertNotNull($liquidacion);
        $this->assertSame(['2026-10', '2026-11', '2026-12', '2026-T4'], array_column($datos->periodosDisponibles(), 'id'));
        $this->assertSame('trimestral', $liquidacion['tipo_periodo']);
        $this->assertSame('2026-10-01', $liquidacion['base']['desde']);
        $this->assertSame('2026-12-31', $liquidacion['base']['hasta']);
        $this->assertTrue($liquidacion['es_demo_activa']);
        $this->assertNull($datos->liquidacion('2040-01'));
        $this->assertNull($datos->asesor('2026-T4', 'INEXISTENTE'));
    }

    public function test_el_archivo_de_la_base_corresponde_al_periodo(): void
    {
        $datos = new DatosEjemploComisiones;

        $this->assertSame('facturas_octubre_2026.xlsx', $datos->liquidacion('2026-10')['base']['archivo']);
        $this->assertSame('facturas_noviembre_2026.xlsx', $datos->liquidacion('2026-11', '2026-11')['base']['archivo']);
        $this->assertSame('facturas_cierre_q4_2026_trimestral.xlsx', $datos->liquidacion('2026-T4', '2026-T4')['base']['archivo']);
    }
}
