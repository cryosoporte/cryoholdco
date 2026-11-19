<?php

namespace Tests\Feature\Reportes;

use App\Reportes\Comisiones\Atribucion;
use App\Reportes\Comisiones\BaseFacturasComisiones;
use App\Reportes\Comisiones\ContratoComisionable;
use App\Reportes\Comisiones\PeriodoComision;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BaseFacturasComisionesTest extends TestCase
{
    private int $siguienteId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararFuentes();
        $this->prepararComisiones();

        DB::table('com_servicio_linea')->insert([
            ['servicio' => 'UCU SANGRE', 'linea_cod' => 'UCU'],
            ['servicio' => 'UCU TEJIDO', 'linea_cod' => 'UCU'],
            ['servicio' => 'TAMIZAJE', 'linea_cod' => 'TAMIZAJE'],
        ]);
    }

    /**
     * Una fila de la fuente con valores de una boleta de UCU Sangre de
     * octubre, salvo los indicados.
     *
     * @param  array<string, mixed>  $valores
     */
    private function factura(array $valores = []): void
    {
        DB::table((new FuenteFacturasOracle)->tabla())->insert([
            'id_interno' => $this->siguienteId++,
            'fecha_emision' => '2026-10-15 00:00:00',
            'servicio' => 'UCU SANGRE',
            'numero_contrato_oracle' => 'PE-CR-2026-000001-1',
            'tipo_documento' => '03- Boleta de Venta',
            'aplicacion_transaccion' => null,
            'subtotal_soles' => 1000,
            'representante_ventas' => 'Rosales Cabrera, Adler Abad',
            'captador_final' => 'Cuba Michilot, Claudia Milagros',
            'dueno' => 'COBRADOR 1',
            ...$valores,
        ]);
    }

    /**
     * @return list<ContratoComisionable>
     */
    private function contratosDeOctubre(): array
    {
        return app(BaseFacturasComisiones::class)->contratos(PeriodoComision::mes(2026, 10));
    }

    public function test_un_contrato_con_varios_servicios_de_la_misma_linea_cuenta_una_vez(): void
    {
        $this->factura(['subtotal_soles' => 1500.25, 'fecha_emision' => '2026-10-20 00:00:00']);
        $this->factura(['servicio' => 'UCU TEJIDO', 'subtotal_soles' => 1200.50, 'fecha_emision' => '2026-10-12 00:00:00']);
        $this->factura(['servicio' => 'TAMIZAJE', 'subtotal_soles' => 1355.93]);

        $contratos = $this->contratosDeOctubre();

        $this->assertCount(2, $contratos);
        [$tamizaje, $ucu] = $contratos;
        $this->assertSame('TAMIZAJE', $tamizaje->linea);
        $this->assertSame('UCU', $ucu->linea);
        $this->assertSame('PE-CR-2026-000001-1', $ucu->contrato);
        $this->assertSame(2700.75, $ucu->monto);
        $this->assertSame(2, $ucu->filas);
        $this->assertSame('2026-10-12', $ucu->fechaEmision);
        $this->assertSame('Rosales Cabrera, Adler Abad', $ucu->asesor(Atribucion::Representante));
        $this->assertSame('Cuba Michilot, Claudia Milagros', $ucu->asesor(Atribucion::Captador));
        $this->assertSame('COBRADOR 1', $ucu->asesor(Atribucion::Dueno));
        $this->assertNull($ucu->asesor(Atribucion::Equipo));
    }

    public function test_cuenta_las_facturas_de_todo_el_mes_incluido_el_ultimo_dia(): void
    {
        $this->factura(['numero_contrato_oracle' => 'PRIMER-DIA', 'fecha_emision' => '2026-10-01 00:00:00']);
        $this->factura(['numero_contrato_oracle' => 'ULTIMO-DIA', 'fecha_emision' => '2026-10-31 18:30:00']);
        $this->factura(['numero_contrato_oracle' => 'SEPTIEMBRE', 'fecha_emision' => '2026-09-30 23:59:59']);
        $this->factura(['numero_contrato_oracle' => 'NOVIEMBRE', 'fecha_emision' => '2026-11-01 00:00:00']);

        $this->assertSame(['PRIMER-DIA', 'ULTIMO-DIA'], array_column($this->contratosDeOctubre(), 'contrato'));
    }

    public function test_excluye_notas_de_credito_facturas_aplicadas_y_filas_sin_contrato(): void
    {
        $this->factura(['numero_contrato_oracle' => 'CUENTA']);
        $this->factura(['numero_contrato_oracle' => 'FACTURA', 'tipo_documento' => '01- Factura']);
        $this->factura(['numero_contrato_oracle' => 'NOTA', 'tipo_documento' => '07- Nota de crédito', 'subtotal_soles' => -1000]);
        $this->factura(['numero_contrato_oracle' => 'APLICADA', 'aplicacion_transaccion' => 'Factura #F001-123']);
        $this->factura(['numero_contrato_oracle' => null]);

        $this->assertSame(['CUENTA', 'FACTURA'], array_column($this->contratosDeOctubre(), 'contrato'));
    }

    public function test_los_servicios_sin_linea_no_cuentan_y_se_informan(): void
    {
        $this->factura();
        $this->factura(['servicio' => 'MYPRENATAL']);
        $this->factura(['servicio' => 'MYPRENATAL', 'numero_contrato_oracle' => 'OTRO']);
        $this->factura(['servicio' => 'NIPT - G']);
        $this->factura(['servicio' => 'NIPT - G', 'tipo_documento' => '07- Nota de crédito']);
        $this->factura(['servicio' => 'FIBROBLASTOS', 'fecha_emision' => '2026-09-10 00:00:00']);

        $base = app(BaseFacturasComisiones::class);

        $this->assertSame(['UCU'], array_column($base->contratos(PeriodoComision::mes(2026, 10)), 'linea'));
        $this->assertSame(['MYPRENATAL' => 2, 'NIPT - G' => 1], $base->serviciosSinLinea(PeriodoComision::mes(2026, 10)));
    }

    public function test_un_trimestre_reune_los_tres_meses(): void
    {
        $this->factura(['numero_contrato_oracle' => 'OCTUBRE', 'fecha_emision' => '2026-10-05 00:00:00']);
        $this->factura(['numero_contrato_oracle' => 'DICIEMBRE', 'fecha_emision' => '2026-12-31 10:00:00']);
        $this->factura(['numero_contrato_oracle' => 'ENERO', 'fecha_emision' => '2027-01-01 00:00:00']);

        $contratos = app(BaseFacturasComisiones::class)->contratos(PeriodoComision::trimestre(2026, 4));

        $this->assertSame(['DICIEMBRE', 'OCTUBRE'], array_column($contratos, 'contrato'));
    }
}
