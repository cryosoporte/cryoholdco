<?php

namespace Tests\Feature\Reportes;

use App\Reportes\Comisiones\CalcularComisiones;
use App\Reportes\Comisiones\ComisionCalculada;
use App\Reportes\Comisiones\PeriodoComision;
use App\Reportes\Comisiones\ResultadoComisiones;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las metas son las de COM_META de octubre a diciembre 2026 y los casos
 * reproducen reglas del reporte manual de octubre (ProdCom_10_2026.xlsx).
 */
class CalcularComisionesTest extends TestCase
{
    private int $siguienteFila = 1;

    private int $siguienteContrato = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararFuentes();
        $this->prepararComisiones();

        DB::table('com_servicio_linea')->insert([
            ['servicio' => 'UCU SANGRE', 'linea_cod' => 'UCU'],
            ['servicio' => 'UCU TEJIDO', 'linea_cod' => 'UCU'],
            ['servicio' => 'TAMIZAJE', 'linea_cod' => 'TAMIZAJE'],
            ['servicio' => 'VITALICIO SANGRE', 'linea_cod' => 'ST'],
            ['servicio' => 'VITALICIO TEJIDO', 'linea_cod' => 'ST'],
            ['servicio' => 'ANUALIDAD SANGRE', 'linea_cod' => 'ANUALIDAD'],
        ]);

        $this->meta(1, 'EJECUTIVO DE VENTAS', 'REPRESENTANTE', 'UCU', 15, [[15, 16, 75], [17, 18, 100], [19, 20, 125], [21, null, 150]]);
        $this->meta(3, 'EJECUTIVO DE VENTAS', 'REPRESENTANTE', 'TAMIZAJE', 5, [[5, 9, 50], [10, 14, 75], [15, null, 100]]);
        $this->meta(5, 'EJECUTIVO DE VENTAS', 'REPRESENTANTE', 'UCU', 45, [[45, 50, 75], [51, 56, 100], [57, 62, 125], [63, null, 150]], 'T');
        $this->meta(10, 'REPRESENTANTE DE PROM LIMA', 'CAPTADOR', 'UCU', 15, [[15, null, 50]]);
        $this->meta(30, 'JEFE DE MARKETING', 'EQUIPO', 'UCU', 75, [[75, null, 15]]);
        $this->meta(40, 'EJECUTIVO DE ANUALIDADES', 'DUENO', 'ST', 5, [[5, null, 50]]);
        $this->meta(50, 'JEFE DE OPERACIONES', 'EMPRESA', 'ANUALIDAD', 550000, [[550000, null, 0.0025]], 'M', 'PORCENTAJE', 'MONTO');
        $this->meta(51, 'JEFE DE OPERACIONES', 'EQUIPO', 'ST', 10, [[10, null, 50]]);

        DB::table('users')->insert([
            ['id' => 26, 'name' => 'LISETH CAROLINA RONDON FERNANDEZ', 'email' => 'lrondon@ejemplo.test', 'codigo_netsuite' => 'Rondon Fernandez, Liseth Carolina', 'rol_netsuite' => 'EJECUTIVO DE VENTAS'],
            ['id' => 207, 'name' => 'Claudia Milagros Cuba Michilot', 'email' => 'ccuba@ejemplo.test', 'codigo_netsuite' => 'Cuba Michilot, Claudia Milagros', 'rol_netsuite' => 'EJECUTIVO DE VENTAS'],
            ['id' => 65, 'name' => 'MILAGROS HERRERA IPANAQUE', 'email' => 'mherrera@ejemplo.test', 'codigo_netsuite' => 'Herrera Ipanaque, Milagros', 'rol_netsuite' => 'REPRESENTANTE DE PROM LIMA'],
            ['id' => 95, 'name' => 'DANIEL WALCHEFF GONZALEZ', 'email' => 'dwalcheff@ejemplo.test', 'codigo_netsuite' => 'Walcheff Gonzalez, Daniel Alejandro De Jesus', 'rol_netsuite' => 'JEFE DE MARKETING'],
            ['id' => 107, 'name' => 'MARCOS GABRIEL VASQUEZ MONTEZA', 'email' => 'mvasquez@ejemplo.test', 'codigo_netsuite' => 'COBRADOR 1', 'rol_netsuite' => 'EJECUTIVO DE ANUALIDADES'],
            ['id' => 123, 'name' => 'KATHERINE DIANA CRUZADO PINEDA', 'email' => 'kcruzado@ejemplo.test', 'codigo_netsuite' => 'COBRADOR 2', 'rol_netsuite' => 'EJECUTIVO DE ANUALIDADES'],
            ['id' => 4, 'name' => 'ANDREA GIUSTI DIAZ', 'email' => 'agiusti@ejemplo.test', 'codigo_netsuite' => 'Giusti Diaz, Andrea', 'rol_netsuite' => 'JEFE DE OPERACIONES'],
            ['id' => 300, 'name' => 'SIN ROL', 'email' => null, 'codigo_netsuite' => 'Sin Rol, Usuario', 'rol_netsuite' => null],
        ]);
    }

    /**
     * @param  list<array{float, ?float, float}>  $tramos
     */
    private function meta(int $id, string $rol, string $atribucion, string $linea, float $minimo, array $tramos, string $periodicidad = 'M', string $tipo = 'POR_CONTRATO', string $base = 'CANTIDAD'): void
    {
        DB::table('com_meta')->insert([
            'meta_id' => $id, 'rol_cod' => $rol, 'atribucion' => $atribucion, 'linea_cod' => $linea,
            'periodicidad' => $periodicidad, 'tipo_cod' => $tipo, 'base_tramo' => $base, 'minimo' => $minimo,
            'vig_desde' => '2026-10-01 00:00:00', 'vig_hasta' => '2026-12-31 00:00:00', 'estado' => 'A',
        ]);

        foreach ($tramos as $indice => [$desde, $hasta, $valor]) {
            DB::table('com_meta_tramo')->insert(['meta_id' => $id, 'nro_tramo' => $indice + 1, 'desde' => $desde, 'hasta' => $hasta, 'valor' => $valor]);
        }
    }

    /**
     * Crea $cantidad contratos distintos, cada uno con una fila.
     *
     * @param  array<string, mixed>  $valores
     */
    private function contratos(int $cantidad, array $valores): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $this->fila(['numero_contrato_oracle' => 'PE-CR-'.$this->siguienteContrato++, ...$valores]);
        }
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function fila(array $valores): void
    {
        DB::table((new FuenteFacturasOracle)->tabla())->insert([
            'id_interno' => $this->siguienteFila++,
            'fecha_emision' => '2026-10-15 00:00:00',
            'servicio' => 'UCU SANGRE',
            'tipo_documento' => '03- Boleta de Venta',
            'subtotal_soles' => 1000,
            ...$valores,
        ]);
    }

    private function calcular(PeriodoComision $periodo): ResultadoComisiones
    {
        return app(CalcularComisiones::class)->calcular($periodo);
    }

    private function comision(ResultadoComisiones $resultado, int $asesorId, int $metaId): ComisionCalculada
    {
        foreach ($resultado->delAsesor($asesorId) as $comision) {
            if ($comision->resultado->meta->id === $metaId) {
                return $comision;
            }
        }

        $this->fail("El asesor {$asesorId} no tiene la meta {$metaId}.");
    }

    public function test_cada_ejecutivo_comisiona_por_los_contratos_donde_es_representante(): void
    {
        $this->contratos(15, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina', 'captador_final' => 'Herrera Ipanaque, Milagros']);
        $this->contratos(10, ['representante_ventas' => 'Cuba Michilot, Claudia Milagros', 'captador_final' => 'Walcheff Gonzalez, Daniel Alejandro De Jesus']);

        $resultado = $this->calcular(PeriodoComision::mes(2026, 10));

        $rondon = $this->comision($resultado, 26, 1);
        $this->assertSame(15.0, $rondon->resultado->cantidad);
        $this->assertSame(1125.0, $rondon->comision());
        $this->assertSame(0.0, $this->comision($resultado, 207, 1)->comision());
        $this->assertSame(5.0, $this->comision($resultado, 207, 1)->resultado->faltanteParaMinimo());
        $this->assertSame(0.0, $this->comision($resultado, 26, 3)->resultado->cantidad, 'sin Tamizaje queda en 0');
        $this->assertSame(750.0, $this->comision($resultado, 65, 10)->comision(), 'Herrera es captadora de 15 UCU');
        $this->assertSame([], $resultado->delAsesor(300), 'un usuario sin rol no comisiona');
    }

    public function test_el_nombre_de_netsuite_se_compara_sin_espacios_repetidos_ni_mayusculas(): void
    {
        $this->contratos(15, ['representante_ventas' => '  RONDON  Fernandez, liseth carolina ']);

        $this->assertSame(1125.0, $this->comision($this->calcular(PeriodoComision::mes(2026, 10)), 26, 1)->comision());
    }

    public function test_solo_cuenta_contratos_de_personas_con_el_rol_de_la_meta(): void
    {
        $this->contratos(20, ['representante_ventas' => 'Herrera Ipanaque, Milagros', 'captador_final' => 'Walcheff Gonzalez, Daniel Alejandro De Jesus']);

        $resultado = $this->calcular(PeriodoComision::mes(2026, 10));

        $this->assertSame(0.0, $this->comision($resultado, 26, 1)->resultado->cantidad);
        $this->assertSame([30], array_map(
            fn (ComisionCalculada $comision): int => $comision->resultado->meta->id,
            $resultado->delAsesor(95),
        ), 'el jefe de marketing solo tiene la meta de equipo, aunque sea captador');
        $this->assertSame([], $resultado->nombresSinConfigurar, 'Herrera y Walcheff sí están configurados, aunque con otro rol');
    }

    public function test_el_jefe_de_marketing_suma_lo_que_logran_los_ejecutivos_de_venta(): void
    {
        $this->contratos(40, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina']);
        $this->contratos(36, ['representante_ventas' => 'Cuba Michilot, Claudia Milagros']);
        $this->contratos(30, ['representante_ventas' => 'Herrera Ipanaque, Milagros']);

        $jefe = $this->comision($this->calcular(PeriodoComision::mes(2026, 10)), 95, 30);

        $this->assertSame(76.0, $jefe->resultado->cantidad, 'no suma los contratos de una promotora');
        $this->assertSame(1140.0, $jefe->comision());
    }

    public function test_seguridad_total_cuenta_cada_linea_de_servicio_y_suma_al_equipo_de_operaciones(): void
    {
        $this->fila(['numero_contrato_oracle' => 'ST-1', 'servicio' => 'VITALICIO SANGRE', 'dueno' => 'COBRADOR 1']);
        $this->fila(['numero_contrato_oracle' => 'ST-1', 'servicio' => 'VITALICIO TEJIDO', 'dueno' => 'COBRADOR 1']);
        $this->contratos(8, ['servicio' => 'VITALICIO SANGRE', 'dueno' => 'COBRADOR 1']);
        $this->contratos(3, ['servicio' => 'VITALICIO SANGRE', 'dueno' => 'COBRADOR 2']);

        $resultado = $this->calcular(PeriodoComision::mes(2026, 10));

        $this->assertSame(10.0, $this->comision($resultado, 107, 40)->resultado->cantidad);
        $this->assertSame(500.0, $this->comision($resultado, 107, 40)->comision());
        $this->assertSame(0.0, $this->comision($resultado, 123, 40)->comision(), '3 ST no llegan al mínimo de 5');
        $this->assertSame(13.0, $this->comision($resultado, 4, 51)->resultado->cantidad);
        $this->assertSame(650.0, $this->comision($resultado, 4, 51)->comision());
    }

    public function test_la_meta_de_empresa_toma_todo_el_monto_de_la_linea(): void
    {
        $this->contratos(2, ['servicio' => 'ANUALIDAD SANGRE', 'subtotal_soles' => 300000, 'dueno' => 'COBRADOR 1']);

        $operaciones = $this->comision($this->calcular(PeriodoComision::mes(2026, 10)), 4, 50);

        $this->assertSame(600000.0, $operaciones->resultado->monto);
        $this->assertSame(1500.0, $operaciones->comision());
    }

    public function test_informa_los_nombres_de_netsuite_que_nadie_tiene_configurados(): void
    {
        $this->contratos(4, ['representante_ventas' => 'ADNTRO CRIOCORD, USUARIO']);
        $this->contratos(1, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina', 'captador_final' => 'Sari Rodriguez, Diana Carolina']);

        $resultado = $this->calcular(PeriodoComision::mes(2026, 10));

        $this->assertSame(['ADNTRO CRIOCORD, USUARIO' => 4, 'Sari Rodriguez, Diana Carolina' => 1], $resultado->nombresSinConfigurar);
    }

    public function test_el_trimestre_descuenta_lo_cobrado_en_los_meses_y_paga_solo_la_diferencia(): void
    {
        $this->contratos(15, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina', 'fecha_emision' => '2026-10-10 00:00:00']);
        $this->contratos(14, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina', 'fecha_emision' => '2026-11-10 00:00:00']);
        $this->contratos(17, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina', 'fecha_emision' => '2026-12-10 00:00:00']);
        $this->contratos(15, ['representante_ventas' => 'Cuba Michilot, Claudia Milagros', 'fecha_emision' => '2026-10-10 00:00:00']);
        $this->contratos(16, ['representante_ventas' => 'Cuba Michilot, Claudia Milagros', 'fecha_emision' => '2026-11-10 00:00:00']);
        $this->contratos(15, ['representante_ventas' => 'Cuba Michilot, Claudia Milagros', 'fecha_emision' => '2026-12-10 00:00:00']);

        $resultado = $this->calcular(PeriodoComision::trimestre(2026, 4));

        $rondon = $this->comision($resultado, 26, 5);
        $this->assertSame(46.0, $rondon->resultado->cantidad);
        $this->assertSame(3450.0, $rondon->comision(), '46 × 75');
        $this->assertSame(2825.0, $rondon->adelanto, 'octubre 15 × 75 + noviembre 0 + diciembre 17 × 100');
        $this->assertSame(625.0, $rondon->pendiente());

        $cuba = $this->comision($resultado, 207, 5);
        $this->assertSame(3450.0, $cuba->resultado->comision, '46 × 75');
        $this->assertSame(3450.0, $cuba->adelanto, '15 × 75 + 16 × 75 + 15 × 75');
        $this->assertSame(0.0, $cuba->pendiente());

        $this->assertSame(625.0, $resultado->total(), 'en el cierre trimestral solo se paga lo pendiente');
    }
}
