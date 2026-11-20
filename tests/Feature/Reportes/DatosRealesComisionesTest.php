<?php

namespace Tests\Feature\Reportes;

use App\Reportes\Comisiones\DatosComisiones;
use App\Reportes\Comisiones\DatosRealesComisiones;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DatosRealesComisionesTest extends TestCase
{
    private int $siguienteFila = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['reportes.comisiones_demo.datos' => 'reales']);
        $this->travelTo(now()->parse('2026-11-19 10:00:00', 'America/Lima'));
        $this->prepararFuentes();
        $this->prepararComisiones();

        DB::table('com_servicio_linea')->insert([
            ['servicio' => 'UCU SANGRE', 'linea_cod' => 'UCU'],
            ['servicio' => 'ANUALIDAD SANGRE', 'linea_cod' => 'ANUALIDAD'],
        ]);

        $this->meta(1, 'EJECUTIVO DE VENTAS', 'REPRESENTANTE', 'UCU', 15, [[15, 16, 75], [17, null, 100]]);
        $this->meta(41, 'EJECUTIVO DE ANUALIDADES', 'DUENO', 'MOROSO', 3, [[3, 3, 0.05]], 'PORCENTAJE');
        $this->meta(30, 'JEFE DE MARKETING', 'EQUIPO', 'UCU', 75, [[75, null, 15]]);
        $this->meta(50, 'JEFE DE OPERACIONES', 'EMPRESA', 'ANUALIDAD', 550000, [[550000, null, 0.0025]], 'PORCENTAJE', 'MONTO');

        DB::table('users')->insert([
            ['id' => 26, 'name' => 'LISETH CAROLINA RONDON FERNANDEZ', 'email' => 'lrondon@criocord.test', 'codigo_netsuite' => 'Rondon Fernandez, Liseth Carolina', 'rol_netsuite' => 'EJECUTIVO DE VENTAS'],
            ['id' => 207, 'name' => 'Claudia Milagros Cuba Michilot', 'email' => 'ccuba@criocord.test', 'codigo_netsuite' => 'Cuba Michilot, Claudia Milagros', 'rol_netsuite' => 'EJECUTIVO DE VENTAS'],
            ['id' => 95, 'name' => 'DANIEL WALCHEFF GONZALEZ', 'email' => 'dwalcheff@criocord.test', 'codigo_netsuite' => 'Walcheff Gonzalez, Daniel', 'rol_netsuite' => 'JEFE DE MARKETING'],
            ['id' => 107, 'name' => 'MARCOS GABRIEL VASQUEZ MONTEZA', 'email' => 'mvasquez@criocord.test', 'codigo_netsuite' => 'COBRADOR 1', 'rol_netsuite' => 'EJECUTIVO DE ANUALIDADES'],
            ['id' => 4, 'name' => 'ANDREA GIUSTI DIAZ', 'email' => 'agiusti@criocord.test', 'codigo_netsuite' => 'Giusti Diaz, Andrea', 'rol_netsuite' => 'JEFE DE OPERACIONES'],
        ]);

        $this->contratos(15, ['representante_ventas' => 'Rondon Fernandez, Liseth Carolina']);
        $this->contratos(10, ['representante_ventas' => 'Cuba Michilot, Claudia Milagros']);
        $this->contratos(1, ['representante_ventas' => 'ADNTRO CRIOCORD, USUARIO']);
        $this->contratos(2, ['servicio' => 'ANUALIDAD SANGRE', 'subtotal_soles' => 150000, 'dueno' => 'COBRADOR 1']);
        $this->contratos(1, ['servicio' => 'MYPRENATAL']);
    }

    /**
     * @param  list<array{float, ?float, float}>  $tramos
     */
    private function meta(int $id, string $rol, string $atribucion, string $linea, float $minimo, array $tramos, string $tipo = 'POR_CONTRATO', string $base = 'CANTIDAD'): void
    {
        DB::table('com_meta')->insert([
            'meta_id' => $id, 'rol_cod' => $rol, 'atribucion' => $atribucion, 'linea_cod' => $linea,
            'periodicidad' => 'M', 'tipo_cod' => $tipo, 'base_tramo' => $base, 'minimo' => $minimo,
            'vig_desde' => '2026-10-01 00:00:00', 'vig_hasta' => '2026-12-31 00:00:00', 'estado' => 'A',
        ]);

        foreach ($tramos as $indice => [$desde, $hasta, $valor]) {
            DB::table('com_meta_tramo')->insert(['meta_id' => $id, 'nro_tramo' => $indice + 1, 'desde' => $desde, 'hasta' => $hasta, 'valor' => $valor]);
        }
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function contratos(int $cantidad, array $valores): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $fila = $this->siguienteFila++;
            DB::table((new FuenteFacturasOracle)->tabla())->insert([
                'id_interno' => $fila,
                'numero_contrato_oracle' => "PE-CR-{$fila}",
                'fecha_emision' => '2026-10-25 00:00:00',
                'servicio' => 'UCU SANGRE',
                'tipo_documento' => '03- Boleta de Venta',
                'subtotal_soles' => 1000,
                ...$valores,
            ]);
        }
    }

    public function test_ofrece_los_meses_desde_octubre_hasta_el_actual_y_su_trimestre(): void
    {
        $datos = app(DatosComisiones::class);

        $this->assertInstanceOf(DatosRealesComisiones::class, $datos);
        $this->assertSame('2026-10', $datos->periodoInicial());
        $this->assertSame([
            ['id' => '2026-10', 'etiqueta' => 'Octubre 2026'],
            ['id' => '2026-11', 'etiqueta' => 'Noviembre 2026'],
            ['id' => '2026-T4', 'etiqueta' => 'Cierre Q4 2026 (trimestral)'],
        ], $datos->periodosDisponibles());
        $this->assertNull($datos->liquidacion('2026-12', '2026-10'), 'diciembre todavía no empieza');
        $this->assertNull($datos->liquidacion('2026-09', '2026-10'), 'antes de las metas no hay liquidación');
        $this->assertNull($datos->liquidacion('octubre', '2026-10'));
    }

    public function test_arma_la_liquidacion_por_cargo_con_el_calculo_real(): void
    {
        $liquidacion = app(DatosComisiones::class)->liquidacion('2026-10', '2026-10');

        $this->assertSame('Octubre 2026', $liquidacion['periodo']);
        $this->assertTrue($liquidacion['es_demo_activa']);
        $this->assertSame(['EJEC', 'ANUAL', 'OPE', 'MKT'], array_column($liquidacion['cargos'], 'codigo'));
        $this->assertSame(1125.0, $liquidacion['total'], 'UCU de Rondón');
        $this->assertSame(1, $liquidacion['colaboradores_con_comision']);
        $this->assertSame(5, $liquidacion['colaboradores']);
        $this->assertSame(28, $liquidacion['base']['filas'], 'la fila de MYPRENATAL no tiene línea');
        $this->assertSame(1, $liquidacion['base']['servicios_sin_configurar']);
        $this->assertSame(1, $liquidacion['base']['nombres_sin_configurar']);

        $ventas = $liquidacion['cargos'][0];
        $this->assertSame(['UCU (15)'], $ventas['columnas'], 'el bono pronta venta está apagado');
        $this->assertSame(['Claudia Milagros Cuba Michilot', 'Liseth Carolina Rondon Fernandez'], array_column($ventas['filas'], 'nombre'));
        $this->assertSame([['texto' => '15 / 15', 'alcanzo' => true]], $ventas['filas'][1]['celdas']);
        $this->assertSame(1125.0, $ventas['total']);

        $rondon = array_column($liquidacion['asesores'], null, 'id')['26'];
        $this->assertSame('liseth.carolina.rondon.fernandez@ejemplo.test', $rondon['correo'], 'los correos siguen siendo de ejemplo');
        $this->assertSame('Liseth', $rondon['nombre_corto']);
        $this->assertSame([['concepto' => 'UCU', 'logrado' => '15', 'cuota' => '15', 'tramo' => '15–16 × S/ 75', 'comision' => 1125.0]], $rondon['lineas']);

        $cuba = array_column($liquidacion['asesores'], null, 'id')['207'];
        $this->assertSame('Te faltaron 5 contratos de UCU para la cuota de 15.', $cuba['nota']);
        $daniel = array_column($liquidacion['asesores'], null, 'id')['95'];
        $this->assertSame('Al equipo le faltaron 50 contratos de UCU para la cuota de 75.', $daniel['nota']);
        $giusti = array_column($liquidacion['asesores'], null, 'id')['4'];
        $this->assertSame('Te faltaron S/ 250,000 en Anualidades para la cuota de S/ 550,000.', $giusti['nota']);

        $marcos = array_column($liquidacion['asesores'], null, 'id')['107'];
        $this->assertSame(['Morosos (3+ años)'], $liquidacion['cargos'][1]['columnas']);
        $this->assertSame([['texto' => 'S/ 0.00', 'alcanzo' => false]], $liquidacion['cargos'][1]['filas'][0]['celdas']);
        $this->assertSame('Sin anualidades morosas en el período', $marcos['lineas'][0]['tramo']);

        $operaciones = $liquidacion['cargos'][2];
        $this->assertSame([['texto' => 'S/ 300,000.00', 'alcanzo' => false]], $operaciones['filas'][0]['celdas']);
        $this->assertSame(['UCU equipo (75)'], $liquidacion['cargos'][3]['columnas']);
    }

    public function test_los_puntos_a_revisar_avisan_lo_que_falta_configurar(): void
    {
        $liquidacion = app(DatosComisiones::class)->liquidacion('2026-10', '2026-10');

        $this->assertSame([
            '«ADNTRO CRIOCORD, USUARIO» figura en 1 contrato y ningún usuario lo tiene como CODIGO_NETSUITE: no se le asigna comisión.',
            'El servicio «MYPRENATAL» tiene 1 fila sin línea en COM_SERVICIO_LINEA: no cuenta para comisiones.',
            '2 anualidades del período no traen en la Nota las fechas que cubren: no se evalúan como morosas.',
            'UCU del equipo: 25, faltaron 50 para la cuota de 75.',
            'Anualidades de la empresa: S/ 300,000.00, faltaron S/ 250,000 para la cuota de S/ 550,000.',
            '4 colaboradores no alcanzaron la cuota mínima en ninguna línea.',
        ], $liquidacion['puntos_revision']);
        $this->assertSame(['UCU del equipo', 'Anualidades de la empresa'], array_column(array_slice($liquidacion['indicadores'], 2), 'etiqueta'));
    }

    public function test_la_pantalla_muestra_la_liquidacion_calculada(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'name' => 'Ana Operaciones', 'empresa' => '001']));

        $this->get(route('reportes.comisiones'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina->component('comisiones')
                ->has('periodos_disponibles', 3)
                ->has('liquidaciones', 1)
                ->where('liquidaciones.0.id', '2026-10')
                ->where('liquidaciones.0.total', 1125));

        $this->get(route('reportes.comisiones.liquidaciones.mostrar', '2026-10'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina->component('comisiones-liquidacion')
                ->where('liquidacion.estado', 'borrador')
                ->where('permisos.puede_procesar', true)
                ->has('liquidacion.asesores', 5));

        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', '26']))->assertOk()
            ->assertSee('1,125.00');
        $this->get(route('reportes.comisiones.liquidaciones.mostrar', '2026-12'))->assertNotFound();
    }
}
