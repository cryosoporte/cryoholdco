<?php

namespace Tests\Feature\Reportes;

use App\Administracion\RolSistema;
use App\Jobs\EnviarLiquidacionJob;
use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use App\Models\FuenteCarga;
use App\Models\LiquidacionComision;
use App\Models\User;
use App\Reportes\Comisiones\AprobarLiquidacion;
use App\Reportes\Comisiones\BonoProntaVenta;
use App\Reportes\Comisiones\CierreTrimestral;
use App\Reportes\Comisiones\DatosRealesComisiones;
use App\Reportes\Comisiones\EnviarCorreosComisiones;
use App\Reportes\Comisiones\EnviarLiquidacionAlPersonal;
use App\Reportes\Comisiones\LiquidacionesComisiones;
use App\Reportes\Comisiones\PeriodoComision;
use App\Reportes\Comisiones\ProcesarLiquidacion;
use App\Reportes\Comisiones\RechazarLiquidacion;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class LiquidacionesComisionesTest extends TestCase
{
    private User $administrador;

    private User $gerencia;

    private string $facturas;

    protected function setUp(): void
    {
        parent::setUp();
        // El envío al personal se encola en redis_reportes; en las pruebas corre en la misma petición.
        config(['queue.connections.redis_reportes' => ['driver' => 'sync']]);
        Mail::fake();
        $this->travelTo(now()->parse('2026-11-23 09:30:00', 'America/Lima'));
        $this->prepararFuentes();
        $this->prepararCargasFuentes();
        $this->prepararComisiones();
        $this->prepararLiquidaciones();
        $this->facturas = (new FuenteFacturasOracle)->tabla();
        $this->administrador = UserFactory::new()->make(['id' => 2, 'name' => 'Administración', 'empresa' => '001']);
        $this->gerencia = UserFactory::new()->make(['id' => 156, 'name' => 'Gerencia', 'empresa' => '002']);
        $this->asignarRol(2, RolSistema::Administrador);
        $this->asignarRol(156, RolSistema::Gerencia);
        foreach (['Alba', 'Beto'] as $indice => $nombre) {
            DB::table('users')->insert(['id' => 10 + $indice, 'name' => $nombre, 'codigo_netsuite' => $nombre, 'rol_netsuite' => 'EJECUTIVO DE VENTAS']);
        }
        DB::table('com_meta')->insert([
            'meta_id' => 1, 'rol_cod' => 'EJECUTIVO DE VENTAS', 'atribucion' => 'REPRESENTANTE', 'linea_cod' => 'UCU',
            'periodicidad' => 'M', 'tipo_cod' => 'POR_CONTRATO', 'base_tramo' => 'CANTIDAD', 'minimo' => 1,
            'vig_desde' => '2026-10-01', 'estado' => 'A',
        ]);
        DB::table('com_meta_tramo')->insert(['meta_id' => 1, 'nro_tramo' => 1, 'desde' => 1, 'valor' => 75]);
        DB::table('com_servicio_linea')->insert(['servicio' => 'UCU SANGRE', 'linea_cod' => 'UCU']);
        foreach ([1, 2] as $id) {
            DB::table($this->facturas)->insert([
                'id_interno' => $id, 'numero_contrato_oracle' => 'PE-CR-1', 'servicio' => 'UCU SANGRE',
                'fecha_emision' => "2026-10-2$id 10:00:00", 'subtotal_soles' => 10.50, 'representante_ventas' => 'Alba',
            ]);
        }
    }

    private function procesar(): LiquidacionComision
    {
        return app(ProcesarLiquidacion::class)->ejecutar('2026-10', $this->administrador);
    }

    public function test_procesar_guarda_una_sola_foto_con_carga_detalle_contratos_evento_y_correo(): void
    {
        foreach ([FuenteCarga::COMPLETADA, FuenteCarga::COMPLETADA, FuenteCarga::FALLIDA] as $indice => $estado) {
            DB::table('fuentes_cargas')->insert([
                'id' => $indice + 1, 'codigo_fuente' => FuenteFacturasOracle::CODIGO, 'estado' => $estado,
                'archivo' => 'facturas.xlsx', 'cargado_por' => 'Sistema', 'usuario_id' => 2,
                'fecha_recepcion' => '2026-11-01', 'fecha_fin' => '2026-11-02',
            ]);
        }
        $liq = $this->procesar();
        $this->assertDatabaseHas('com_liq', ['id' => $liq->id, 'periodo' => '2026-10', 'tipo' => 'M', 'estado' => 'PROCESADA', 'carga_id' => 2, 'total' => 75]);
        $this->assertDatabaseCount('com_liq_detalle', 2);
        $this->assertDatabaseHas('com_liq_detalle', ['liq_id' => $liq->id, 'meta_id' => 1, 'user_id' => 10, 'cantidad' => 1, 'monto' => 21, 'tramo_valor' => 75, 'adelanto' => null, 'a_pagar' => 75]);
        $this->assertEquals($liq->total, DB::table('com_liq_detalle')->sum('a_pagar'));
        $this->assertDatabaseCount('com_liq_contrato', 1);
        $this->assertDatabaseHas('com_liq_contrato', ['numero_contrato' => 'PE-CR-1', 'servicio' => null, 'linea' => 'UCU', 'user_id' => 10, 'filas' => 2, 'monto' => 21, 'fecha_emision' => '2026-10-21 00:00:00']);
        $this->assertDatabaseHas('com_liq_evento', ['accion' => 'PROCESO', 'liq_id' => $liq->id, 'user_id' => 2, 'empresa' => '001', 'nombre' => 'Administración', 'fecha' => '2026-11-23 09:30:00']);
        $this->assertSame(75.0, $liq->foto()['total']);
        $this->assertCount(2, $liq->foto()['asesores']);
        $this->validacion(fn () => $this->procesar(), 'accion', 'Este período ya fue procesado.');
        $this->assertDatabaseCount('com_liq', 1);
        Mail::assertSent(ComisionesParaAprobacion::class, fn ($correo) => $correo->hasTo('gerencia@ejemplo.test'));
        Mail::assertSentCount(1);
    }

    public function test_si_falla_el_aviso_a_gerencia_se_revierte_toda_la_liquidacion(): void
    {
        $this->mock(EnviarCorreosComisiones::class)->shouldReceive('gerencia')->once()->andThrow(new RuntimeException('SMTP de prueba'));
        $this->validacion(fn () => $this->procesar(), 'accion', 'No se pudo enviar el correo a gerencia. Vuelve a intentarlo.');
        foreach (['com_liq', 'com_liq_detalle', 'com_liq_contrato', 'com_liq_evento', 'com_liq_envio'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }

    public function test_se_guardan_por_separado_el_bono_y_los_contratos_morosos_con_sus_servicios(): void
    {
        $this->app->instance(BonoProntaVenta::class, new BonoProntaVenta(1));
        foreach (range(20, 25) as $id) {
            DB::table('users')->insert(['id' => $id, 'name' => "Asesor $id", 'codigo_netsuite' => "Asesor $id", 'rol_netsuite' => 'EJECUTIVO DE VENTAS']);
        }
        DB::table('users')->insert(['id' => 12, 'name' => 'Marcos', 'codigo_netsuite' => 'COBRADOR 1', 'rol_netsuite' => 'EJECUTIVO DE ANUALIDADES']);
        DB::table('com_meta')->insert([
            'meta_id' => 41, 'rol_cod' => 'EJECUTIVO DE ANUALIDADES', 'atribucion' => 'DUENO', 'linea_cod' => 'MOROSO',
            'periodicidad' => 'M', 'tipo_cod' => 'PORCENTAJE', 'base_tramo' => 'CANTIDAD', 'minimo' => 3,
            'vig_desde' => '2026-10-01', 'estado' => 'A',
        ]);
        DB::table('com_meta_tramo')->insert(['meta_id' => 41, 'nro_tramo' => 1, 'desde' => 3, 'valor' => 0.05]);
        foreach (['ANUALIDAD SANGRE', 'ANUALIDAD TEJIDO'] as $indice => $servicio) {
            foreach ([1, 2, 3] as $fila) {
                DB::table($this->facturas)->insert([
                    'id_interno' => 10 * ($indice + 1) + $fila, 'numero_contrato_oracle' => 'PE-LV-2', 'servicio' => $servicio,
                    'fecha_emision' => "2026-10-2$fila 12:00:00", 'inicio_periodo' => '2021-10-01',
                    'importe' => 100, 'dueno' => 'COBRADOR 1',
                ]);
            }
        }

        $liq = $this->procesar();
        $this->assertGreaterThan(4000, strlen($liq->desglose));
        $this->assertEquals(605, $liq->total);
        $this->assertDatabaseHas('com_liq_detalle', ['user_id' => 10, 'meta_id' => 0, 'linea' => 'PRONTA_VENTA', 'a_pagar' => 500]);
        $this->assertDatabaseHas('com_liq_detalle', ['user_id' => 12, 'meta_id' => 41, 'cantidad' => 2, 'monto' => 600, 'a_pagar' => 30]);
        $this->assertDatabaseCount('com_liq_contrato', 4);
        foreach (['ANUALIDAD SANGRE', 'ANUALIDAD TEJIDO'] as $servicio) {
            $this->assertDatabaseHas('com_liq_contrato', [
                'liq_id' => $liq->id, 'user_id' => 12, 'linea' => 'MOROSO', 'numero_contrato' => 'PE-LV-2',
                'servicio' => $servicio, 'fecha_emision' => '2026-10-21 00:00:00', 'monto' => 300, 'filas' => 3,
            ]);
        }
    }

    #[TestWith(['2026-11'])]
    #[TestWith(['2026-12'])]
    #[TestWith(['2026-09'])]
    public function test_no_procesa_un_periodo_fuera_de_los_meses_cerrados_disponibles(string $periodo): void
    {
        $this->validacion(fn () => app(ProcesarLiquidacion::class)->ejecutar($periodo, $this->administrador));
        $this->assertDatabaseCount('com_liq', 0);
        Mail::assertNothingSent();
    }

    public function test_el_rechazo_conserva_el_intento_y_un_nuevo_proceso_toma_la_fuente_actual(): void
    {
        $liq = $this->procesar();
        $rechazar = app(RechazarLiquidacion::class);
        foreach ([' ', 'Es corto', str_repeat('x', 501)] as $comentario) {
            $this->validacion(fn () => $rechazar->ejecutar($liq->id, $this->gerencia, $comentario), 'comentario');
        }
        $this->assertDatabaseCount('com_liq_evento', 1);
        $rechazar->ejecutar($liq->id, $this->gerencia, 'Revisar el contrato de octubre.');
        $consultas = app(LiquidacionesComisiones::class);
        $this->assertNull($consultas->activa('2026-10'));
        $this->assertTrue($liq->is($consultas->ultimaRechazada('2026-10')));
        $this->assertDatabaseHas('com_liq_evento', ['accion' => 'RECHAZO', 'user_id' => 156, 'empresa' => '002', 'comentario' => 'Revisar el contrato de octubre.']);
        $historial = array_column($consultas->historial(app(DatosRealesComisiones::class)), null, 'id');
        $this->assertSame('rechazada', $historial['2026-10']['estado']);
        $this->assertNull($historial['2026-11']['total']);
        $this->validacion(fn () => app(AprobarLiquidacion::class)->ejecutar($liq->id, $this->gerencia));
        DB::table($this->facturas)->delete();
        $nueva = $this->procesar();
        $this->assertNotEquals($liq->id, $nueva->id);
        $this->assertEquals(0, $nueva->total);
        $this->assertEquals(75, $liq->fresh()->total);
        $this->assertTrue($nueva->is($consultas->activa('2026-10')));
    }

    public function test_el_cierre_identifica_los_meses_pendientes_y_no_guarda_ni_envia(): void
    {
        $this->travelTo(now()->parse('2027-01-02', 'America/Lima'));
        $procesar = app(ProcesarLiquidacion::class);
        $octubre = $this->procesar();
        app(AprobarLiquidacion::class)->ejecutar($octubre->id, $this->gerencia);
        $noviembre = $procesar->ejecutar('2026-11', $this->administrador);
        app(RechazarLiquidacion::class)->ejecutar($noviembre->id, $this->gerencia, 'Corregir el detalle.');
        $procesar->ejecutar('2026-12', $this->administrador);
        $this->assertSame(['2026-11', '2026-12'], app(CierreTrimestral::class)->mesesPendientes(PeriodoComision::trimestre(2026, 4)));
        Mail::fake();
        $this->validacion(fn () => $procesar->ejecutar('2026-T4', $this->administrador), 'accion',
            'Falta aprobar las liquidaciones de estos meses: 2026-11, 2026-12.');
        $this->assertDatabaseMissing('com_liq', ['periodo' => '2026-T4']);
        Mail::assertNothingSent();
    }

    public function test_el_cambio_de_cargo_conserva_solo_sus_meses_y_la_cuota_trimestral_completa(): void
    {
        $this->travelTo(now()->parse('2027-01-02', 'America/Lima'));
        $ejecutivo = 'EJECUTIVO DE VENTAS';
        $promotor = 'REPRESENTANTE DE PROM LIMA';
        foreach ([[2, $promotor, 'CAPTADOR', 'M', 1, 50], [3, $ejecutivo, 'REPRESENTANTE', 'T', 3, 200]] as [$id, $rol, $atribucion, $periodicidad, $minimo, $valor]) {
            DB::table('com_meta')->insert([
                'meta_id' => $id, 'rol_cod' => $rol, 'atribucion' => $atribucion, 'linea_cod' => 'UCU',
                'periodicidad' => $periodicidad, 'tipo_cod' => 'POR_CONTRATO', 'base_tramo' => 'CANTIDAD',
                'minimo' => $minimo, 'vig_desde' => '2026-10-01', 'estado' => 'A',
            ]);
            DB::table('com_meta_tramo')->insert(['meta_id' => $id, 'nro_tramo' => 1, 'desde' => $minimo, 'valor' => $valor]);
        }
        foreach ([[3, '2026-11-01', 'PE-CR-1'], [4, '2026-11-02', 'PE-LV-2'], [5, '2026-12-01', 'PE-CR-3']] as [$id, $fecha, $contrato]) {
            DB::table($this->facturas)->insert(['id_interno' => $id, 'numero_contrato_oracle' => $contrato,
                'servicio' => 'UCU SANGRE', 'fecha_emision' => $fecha.' 00:00:00', 'subtotal_soles' => 40,
                'representante_ventas' => 'Alba', 'captador_final' => 'Alba']);
        }

        $procesar = app(ProcesarLiquidacion::class);
        foreach (['2026-10', '2026-11', '2026-12'] as $mes) {
            if ($mes === '2026-12') {
                DB::table('users')->where('id', 10)->update(['rol_netsuite' => $promotor]);
            }
            $mensual = $procesar->ejecutar($mes, $this->administrador);
            app(AprobarLiquidacion::class)->ejecutar($mensual->id, $this->gerencia);
        }
        $this->assertDatabaseHas('com_liq_detalle', ['liq_id' => $mensual->id, 'user_id' => 10, 'rol_netsuite' => $promotor, 'comision' => 50]);
        $cierre = $procesar->ejecutar('2026-T4', $this->administrador);
        $this->assertDatabaseHas('com_liq_detalle', ['liq_id' => $cierre->id, 'user_id' => 10, 'rol_netsuite' => $ejecutivo,
            'minimo' => 3, 'cantidad' => 2, 'monto' => 101, 'adelanto' => 225, 'comision' => 0, 'a_pagar' => 0]);
        $this->assertDatabaseHas('com_liq_contrato', ['liq_id' => $cierre->id, 'user_id' => 10,
            'numero_contrato' => 'PE-CR-1', 'monto' => 61, 'filas' => 3]);
        $this->assertDatabaseMissing('com_liq_contrato', ['liq_id' => $cierre->id, 'numero_contrato' => 'PE-CR-3']);
    }

    public function test_aprobar_congela_la_foto_y_los_correos_usan_sus_datos(): void
    {
        $liq = $this->procesar();
        $aprobar = app(AprobarLiquidacion::class);
        $envio = app(EnviarLiquidacionAlPersonal::class);
        $this->validacion(fn () => $envio->solicitar($liq->id, $this->administrador));
        $aprobar->ejecutar($liq->id, $this->gerencia);
        Mail::assertNotSent(DetalleComisionAsesor::class);
        $this->validacion(fn () => $aprobar->ejecutar($liq->id, $this->gerencia));
        $this->validacion(fn () => app(RechazarLiquidacion::class)->ejecutar($liq->id, $this->gerencia, 'Revisar el contrato.'));
        $this->validacion(fn () => $envio->solicitar($liq->id, $this->administrador, true));
        DB::table($this->facturas)->delete();
        DB::table('users')->update(['name' => 'Modificado']);
        DB::table('com_meta_tramo')->update(['valor' => 1000]);
        $this->assertSame($liq->foto(), $liq->fresh()->foto());
        $this->assertSame(2, $envio->solicitar($liq->id, $this->administrador), 'encola un correo por colaborador');
        Mail::assertSent(DetalleComisionAsesor::class, fn ($correo) => $correo->hasTo('alba@ejemplo.test') && str_contains($correo->envelope()->subject, '75.00'));
        $this->assertSame('ENVIADA', $liq->fresh()->estado);
        $this->validacion(fn () => $envio->solicitar($liq->id, $this->administrador));
        $this->validacion(fn () => $envio->solicitar($liq->id, $this->administrador, true));
        $consultas = app(LiquidacionesComisiones::class);
        $this->assertSame(['PROCESO', 'APROBACION', 'ENVIO'], $consultas->eventos($liq->id)->pluck('accion')->all());
        $this->assertSame(['2026-10' => $liq->id], $consultas->mesesAprobados(PeriodoComision::trimestre(2026, 4)));
    }

    public function test_el_fallo_de_un_destinatario_permite_continuar_y_reintentar_solo_su_correo(): void
    {
        $liq = $this->procesar();
        app(AprobarLiquidacion::class)->ejecutar($liq->id, $this->gerencia);
        $correo = $this->mock(EnviarCorreosComisiones::class);
        $correo->shouldReceive('pausar')->once()->ordered();
        $correo->shouldReceive('asesor')->once()->ordered()->andThrow(new RuntimeException('Fallo simulado'));
        $correo->shouldReceive('pausar')->once()->ordered();
        $correo->shouldReceive('asesor')->once()->ordered();
        $correo->shouldReceive('pausar')->once()->ordered();
        $correo->shouldReceive('asesor')->once()->ordered();
        $operacion = app(EnviarLiquidacionAlPersonal::class);
        $this->assertSame(2, $operacion->solicitar($liq->id, $this->administrador));
        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 10, 'intentos' => 1, 'estado' => 'F', 'fecha_envio' => null]);
        $this->assertSame('ENVIADA', $liq->fresh()->estado, 'termina aunque haya un fallido');
        $this->assertSame(1, $operacion->solicitar($liq->id, $this->administrador, true), 'solo reencola el fallido');
        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 10, 'intentos' => 2, 'estado' => 'E', 'error' => null]);
        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 11, 'intentos' => 1, 'estado' => 'E']);
        $this->assertDatabaseHas('com_liq_evento', ['liq_id' => $liq->id, 'accion' => 'REENVIO', 'user_id' => 2]);
    }

    public function test_solicitar_el_envio_solo_encola_y_bloquea_otro_envio_mientras_hay_pendientes(): void
    {
        Queue::fake();
        $liq = $this->procesar();
        app(AprobarLiquidacion::class)->ejecutar($liq->id, $this->gerencia);
        $envio = app(EnviarLiquidacionAlPersonal::class);

        $this->assertSame(2, $envio->solicitar($liq->id, $this->administrador));

        Queue::assertPushed(EnviarLiquidacionJob::class, fn (EnviarLiquidacionJob $job): bool => $job->liquidacionId === $liq->id
            && $job->connection === 'redis_reportes');
        Mail::assertNotSent(DetalleComisionAsesor::class);
        $this->assertSame('APROBADA', $liq->fresh()->estado, 'sigue aprobada hasta que el worker termine');
        $this->assertSame(2, DB::table('com_liq_envio')->where('liq_id', $liq->id)->where('estado', 'P')->count());
        $this->validacion(fn () => $envio->solicitar($liq->id, $this->administrador));
        $this->validacion(fn () => $envio->solicitar($liq->id, $this->administrador, true));
        Queue::assertPushed(EnviarLiquidacionJob::class, 1);
    }

    public function test_si_el_job_se_interrumpe_los_pendientes_quedan_fallidos_y_se_reenvian(): void
    {
        Queue::fake();
        $liq = $this->procesar();
        app(AprobarLiquidacion::class)->ejecutar($liq->id, $this->gerencia);
        $envio = app(EnviarLiquidacionAlPersonal::class);
        $envio->solicitar($liq->id, $this->administrador);
        $correo = $this->mock(EnviarCorreosComisiones::class);
        $correo->shouldReceive('asesor')->once();
        $correo->shouldReceive('pausar')->once()->ordered();
        $correo->shouldReceive('pausar')->once()->ordered()->andThrow(new RuntimeException('Interrupción de prueba'));
        $job = new EnviarLiquidacionJob($liq->id);

        try {
            $job->handle(app(EnviarLiquidacionAlPersonal::class));
            $this->fail('El envío debía interrumpirse después del primer correo.');
        } catch (RuntimeException $error) {
            $job->failed($error);
        }

        $this->assertSame('ENVIADA', $liq->fresh()->estado);
        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 10, 'estado' => 'E', 'intentos' => 1]);
        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 11, 'estado' => 'F', 'intentos' => 0,
            'error' => EnviarLiquidacionAlPersonal::ERROR_INTERRUPCION]);

        $this->assertSame(1, $envio->solicitar($liq->id, $this->administrador, true));
        $correo->shouldReceive('pausar')->once();
        $correo->shouldReceive('asesor')->once();
        (new EnviarLiquidacionJob($liq->id))->handle(app(EnviarLiquidacionAlPersonal::class));

        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 10, 'estado' => 'E', 'intentos' => 1]);
        $this->assertDatabaseHas('com_liq_envio', ['user_id' => 11, 'estado' => 'E', 'intentos' => 1, 'error' => null]);
        $this->assertSame(['PROCESO', 'APROBACION', 'ENVIO', 'REENVIO'], app(LiquidacionesComisiones::class)->eventos($liq->id)->pluck('accion')->all());
    }

    public function test_si_no_se_puede_encolar_los_correos_quedan_fallidos_para_reenviarlos(): void
    {
        $liq = $this->procesar();
        app(AprobarLiquidacion::class)->ejecutar($liq->id, $this->gerencia);
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Redis no disponible'));

        $this->validacion(fn () => app(EnviarLiquidacionAlPersonal::class)->solicitar($liq->id, $this->administrador));

        $this->assertSame('ENVIADA', $liq->fresh()->estado);
        $this->assertSame(2, DB::table('com_liq_envio')->where('liq_id', $liq->id)->where('estado', 'F')->count());
        Mail::assertNotSent(DetalleComisionAsesor::class);
    }

    private function validacion(callable $accion, string $clave = 'accion', ?string $mensaje = null): void
    {
        try {
            $accion();
            $this->fail('La acción debió fallar por validación.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($clave, $error->errors());
            if ($mensaje !== null) {
                $this->assertSame([$mensaje], $error->errors()[$clave]);
            }
        }
    }
}
