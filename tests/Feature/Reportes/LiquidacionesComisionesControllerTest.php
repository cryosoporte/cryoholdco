<?php

namespace Tests\Feature\Reportes;

use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use App\Models\LiquidacionComision;
use App\Reportes\Comisiones\EnviarCorreosComisiones;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Database\Factories\UserFactory;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class LiquidacionesComisionesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // El envío al personal se encola en redis_reportes; en las pruebas corre en la misma petición.
        config(['queue.connections.redis_reportes' => ['driver' => 'sync']]);
        $this->withoutVite();
        $this->travelTo(now()->parse('2026-11-23 09:30:00', 'America/Lima'));
        $this->prepararFuentes();
        $this->prepararCargasFuentes();
        $this->prepararComisiones();
        $this->prepararLiquidaciones();
        $this->asignarRol(1, 'ADMINISTRADOR');
        $this->asignarRol(2, 'GERENCIA');
        foreach (['Alba', 'Beto'] as $i => $nombre) {
            DB::table('users')->insert(['id' => 10 + $i, 'name' => $nombre, 'codigo_netsuite' => $nombre, 'rol_netsuite' => 'EJECUTIVO DE VENTAS']);
        }
        DB::table('com_meta')->insert([
            'meta_id' => 1, 'rol_cod' => 'EJECUTIVO DE VENTAS', 'atribucion' => 'REPRESENTANTE', 'linea_cod' => 'UCU',
            'periodicidad' => 'M', 'tipo_cod' => 'POR_CONTRATO', 'base_tramo' => 'CANTIDAD', 'minimo' => 1,
            'vig_desde' => '2026-10-01', 'estado' => 'A',
        ]);
        DB::table('com_meta_tramo')->insert(['meta_id' => 1, 'nro_tramo' => 1, 'desde' => 1, 'valor' => 75]);
        DB::table('com_servicio_linea')->insert(['servicio' => 'UCU SANGRE', 'linea_cod' => 'UCU']);
        DB::table((new FuenteFacturasOracle)->tabla())->insert([
            'id_interno' => 1, 'numero_contrato_oracle' => 'PE-CR-1', 'servicio' => 'UCU SANGRE',
            'fecha_emision' => '2026-10-20', 'subtotal_soles' => 50, 'representante_ventas' => 'Alba',
        ]);
    }

    private function administracion(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'name' => 'Administración', 'empresa' => '001']));
    }

    private function gerencia(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 2, 'name' => 'Gerencia', 'empresa' => '002']));
    }

    private function detalle(string $id = '2026-10'): TestResponse
    {
        return $this->get(route('reportes.comisiones.liquidaciones.mostrar', $id));
    }

    /** @param array<string, mixed> $datos */
    private function accion(string $accion, array $datos = [], string $id = '2026-10'): TestResponse
    {
        $version = LiquidacionComision::query()->where('periodo', $id)->max('id');

        return $this->from(route('reportes.comisiones.liquidaciones.mostrar', $id))
            ->post(route("reportes.comisiones.liquidaciones.$accion", $id), $datos + ['liquidacion_id' => $version]);
    }

    public function test_historial_borrador_mes_abierto_y_trimestre_pendiente(): void
    {
        $this->administracion();
        $this->get(route('reportes.comisiones'))->assertOk()->assertInertia(fn (Assert $p): Assert => $p
            ->missing('rol')->has('liquidaciones', 3)->where('liquidaciones.0.total', null));
        $this->detalle()->assertOk()->assertInertia(fn (Assert $p): Assert => $p->missing('rol')
            ->where('liquidacion.total', 75)->where('permisos', [
                'puede_procesar' => true, 'puede_aprobar' => false, 'puede_rechazar' => false,
                'puede_enviar' => false, 'puede_reenviar' => false,
            ]));
        $this->detalle('2026-11')->assertInertia(fn (Assert $p): Assert => $p->where('permisos.puede_procesar', false));
        $this->detalle('2026-T4')->assertInertia(fn (Assert $p): Assert => $p->where('liquidacion', null)
            ->where('meses_pendientes', ['2026-10', '2026-11', '2026-12'])->where('permisos.puede_procesar', false));
        $this->accion('procesar', id: '2026-11')->assertSessionHasErrors('accion');
        $this->accion('procesar', id: '2026-T4')->assertSessionHasErrors('accion');
        $this->detalle('2040-01')->assertNotFound();
        $this->accion('procesar', id: '2040-01')->assertNotFound();
        $this->detalle('octubre')->assertNotFound();
        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2040-01'))->assertNotFound();
        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2026-T4'))->assertNotFound();
        $this->assertDatabaseCount('com_liq', 0);
    }

    public function test_el_historial_muestra_el_anio_actual_y_todos_solo_si_se_pide(): void
    {
        $this->administracion();
        Mail::fake();
        $this->accion('procesar')->assertRedirect();
        $this->travelTo(now()->parse('2027-02-15 09:00:00', 'America/Lima'));

        $this->get(route('reportes.comisiones'))->assertOk()->assertInertia(fn (Assert $p): Assert => $p
            ->where('anio', '2027')->where('anios', ['2027', '2026'])
            ->where('liquidaciones', fn ($filas): bool => collect($filas)->pluck('id')->all() === ['2027-01', '2027-02', '2027-T1']));
        $this->get(route('reportes.comisiones', ['anio' => '2026']))->assertInertia(fn (Assert $p): Assert => $p
            ->where('anio', '2026')->has('liquidaciones', 4)
            ->where('liquidaciones.0.id', '2026-10')->where('liquidaciones.0.total', 75));
        $this->get(route('reportes.comisiones', ['anio' => 'todos']))->assertInertia(fn (Assert $p): Assert => $p
            ->where('anio', 'todos')->has('liquidaciones', 7));
        $this->get(route('reportes.comisiones', ['anio' => '2025']))->assertSessionHasErrors('anio');
    }

    public function test_las_acciones_exigen_el_rol_antes_de_validar_y_el_estado_correcto(): void
    {
        Mail::fake();
        $this->administracion();
        foreach (['aprobar', 'rechazar'] as $accion) {
            $this->accion($accion)->assertForbidden();
        }
        foreach (['enviar', 'reenviar'] as $accion) {
            $this->accion($accion)->assertSessionHasErrors('accion');
        }
        $this->gerencia();
        foreach (['procesar', 'enviar', 'reenviar'] as $accion) {
            $this->accion($accion)->assertForbidden();
        }
        $this->accion('aprobar')->assertSessionHasErrors('accion');
        $this->accion('rechazar', ['comentario' => 'Revisar el detalle.'])->assertSessionHasErrors('accion');
        $this->administracion();
        $this->accion('procesar')->assertSessionHasNoErrors();
        $this->accion('procesar')->assertSessionHasErrors('accion');
        $this->accion('enviar')->assertSessionHasErrors('accion');
        $this->gerencia();
        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p->where('permisos.puede_aprobar', true)
            ->where('permisos.puede_rechazar', true)->where('permisos.puede_procesar', false)->where('permisos.puede_enviar', false));
        foreach (['', 'Corto', str_repeat('a', 501)] as $comentario) {
            $this->accion('rechazar', compact('comentario'))->assertSessionHasErrors('comentario');
        }
        $version = LiquidacionComision::query()->sole()->id;
        $this->accion('rechazar', ['comentario' => 'Verificar el detalle.'])->assertInertiaFlash('comisiones.tipo', 'aviso');
        $this->administracion();
        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p->where('liquidacion.estado', 'rechazada')
            ->where('liquidacion.historial.1.comentario', 'Verificar el detalle.')->where('permisos.puede_procesar', true));
        $this->accion('procesar')->assertSessionHasNoErrors();
        $this->gerencia();
        $this->accion('aprobar', ['liquidacion_id' => $version])->assertSessionHasErrors('accion');
        $this->accion('aprobar')->assertSessionHasNoErrors();
        Mail::assertNotSent(DetalleComisionAsesor::class);
        $this->accion('aprobar')->assertSessionHasErrors('accion');
        $this->administracion();
        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p->where('permisos.puede_enviar', true)
            ->where('permisos.puede_aprobar', false)->where('liquidacion.historial.3.tipo', 'aprobada'));
        $this->accion('enviar')->assertSessionHasNoErrors();
        $this->accion('enviar')->assertSessionHasErrors('accion');
        $this->accion('reenviar')->assertSessionHasErrors('accion');
        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p->where('liquidacion.estado', 'enviada_personal')
            ->has('liquidacion.envios', 2)->where('liquidacion.historial.4.tipo', 'enviada_personal'));
    }

    public function test_quien_procesa_no_puede_aprobar_ni_rechazar_desde_ninguna_de_sus_cuentas(): void
    {
        Mail::fake();
        $this->crearRol('GESTION_COMISIONES', ['comisiones.ver', 'comisiones.procesar', 'comisiones.aprobar', 'comisiones.rechazar']);
        $cuentas = [
            UserFactory::new()->make(['id' => 3, 'identificacion' => '12345678', 'empresa' => '001']),
            UserFactory::new()->make(['id' => 4, 'identificacion' => ' 12345678 ', 'empresa' => '002']),
        ];
        foreach ($cuentas as $cuenta) {
            DB::table('users')->insert($cuenta->only(['id', 'name', 'email', 'identificacion', 'empresa']));
            $this->asignarRol($cuenta->id, 'GESTION_COMISIONES');
        }
        $this->actingAs($cuentas[0]);
        $this->accion('procesar')->assertSessionHasNoErrors();

        foreach ($cuentas as $cuenta) {
            $this->actingAs($cuenta);
            $this->detalle()->assertOk()->assertInertia(fn (Assert $p): Assert => $p
                ->where('permisos.puede_aprobar', false)
                ->where('permisos.puede_rechazar', false)
                ->where('aviso_separacion', 'No puedes aprobar ni rechazar una liquidación que procesaste.'));
            $this->accion('aprobar')->assertSessionHasErrors(['accion' => 'No puedes aprobar una liquidación que procesaste.']);
            $this->accion('rechazar', ['comentario' => 'Verificar el detalle.'])
                ->assertSessionHasErrors(['accion' => 'No puedes rechazar una liquidación que procesaste.']);
        }
        $this->assertDatabaseHas('com_liq', ['periodo' => '2026-10', 'estado' => 'PROCESADA']);
        $this->assertDatabaseCount('com_liq_evento', 1);

        $this->gerencia();
        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p
            ->where('permisos.puede_aprobar', true)
            ->where('permisos.puede_rechazar', true)
            ->where('aviso_separacion', null));
        $this->accion('aprobar')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('com_liq', ['periodo' => '2026-10', 'estado' => 'APROBADA']);
    }

    public function test_detalle_historial_y_vistas_previas_leen_la_foto_y_solo_muestran_al_destinatario_del_correo(): void
    {
        Mail::fake();
        $this->administracion();
        $this->accion('procesar')->assertSessionHasNoErrors();
        $this->gerencia();
        $this->accion('aprobar')->assertSessionHasNoErrors();
        DB::table((new FuenteFacturasOracle)->tabla())->delete();
        DB::table('fuentes_datos')->update(['actualizado_por' => 'Carga posterior']);
        DB::table('users')->where('id', 10)->update(['name' => 'Nombre cambiado']);
        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p->where('liquidacion.total', 75)->where('liquidacion.asesores.0.nombre', 'Alba')
            ->where('liquidacion.base.actualizacion_fuente.actualizado_por', null));
        $this->get(route('reportes.comisiones'))->assertInertia(fn (Assert $p): Assert => $p->where('liquidaciones.0.total', 75)->where('liquidaciones.0.estado', 'aprobada'));
        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2026-10'))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertSee('S/ 75.00')
            ->assertSee(route('reportes.comisiones.liquidaciones.mostrar', '2026-10'));
        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', '10']))->assertOk()
            ->assertSee('Hola, Alba:')->assertSee('S/ 75.00')->assertDontSee('Beto');
        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', '999']))->assertNotFound();
        Mail::assertSentCount(1);
    }

    public function test_la_vista_previa_de_un_periodo_rechazado_usa_el_calculo_actual_como_la_pantalla(): void
    {
        Mail::fake();
        $this->administracion();
        $this->accion('procesar')->assertSessionHasNoErrors();
        $this->gerencia();
        $this->accion('rechazar', ['comentario' => 'Revisar la base del mes.'])->assertSessionHasNoErrors();
        DB::table((new FuenteFacturasOracle)->tabla())->delete();

        $this->detalle()->assertInertia(fn (Assert $p): Assert => $p->where('liquidacion.estado', 'rechazada')->where('liquidacion.total', 0));
        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2026-10'))->assertOk()
            ->assertSee('S/ 0.00')->assertDontSee('S/ 75.00');
    }

    public function test_una_foto_anterior_al_retiro_de_la_demo_sigue_abriendo_sin_exponer_el_indicador_antiguo(): void
    {
        Mail::fake();
        $this->administracion();
        $this->accion('procesar')->assertSessionHasNoErrors();
        $fila = LiquidacionComision::query()->sole();
        $foto = $fila->foto();
        $this->assertArrayNotHasKey('es_demo_activa', $foto);
        $foto['es_demo_activa'] = false;
        $fila->desglose = json_encode($foto, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $fila->save();

        $this->detalle()->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.total', 75)->where('liquidacion.estado', 'enviada')->missing('liquidacion.es_demo_activa'));
        $this->assertSame($foto, $fila->fresh()->foto());
    }

    public function test_las_pantallas_acciones_y_vistas_previas_requieren_sesion(): void
    {
        $this->get(route('reportes.comisiones'))->assertRedirectToRoute('login');
        $this->detalle()->assertRedirectToRoute('login');

        foreach (['procesar', 'aprobar', 'rechazar', 'enviar', 'reenviar'] as $accion) {
            $this->accion($accion)->assertRedirectToRoute('login');
        }

        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2026-10'))->assertRedirectToRoute('login');
        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', '10']))->assertRedirectToRoute('login');
    }

    public function test_procesar_envia_un_correo_a_gerencia(): void
    {
        $this->administracion();
        Mail::fake();

        $this->accion('procesar')->assertRedirect()->assertInertiaFlash('comisiones.tipo', 'exito');

        Mail::assertSent(ComisionesParaAprobacion::class, 1);
        Mail::assertSent(ComisionesParaAprobacion::class, 'gerencia@ejemplo.test');
        Mail::assertNotSent(DetalleComisionAsesor::class);
    }

    public function test_enviar_distribuye_un_correo_individual_a_cada_asesor(): void
    {
        $this->administracion();
        $asesores = ['alba@ejemplo.test', 'beto@ejemplo.test'];
        Mail::fake();
        $this->accion('procesar')->assertRedirect();
        $this->gerencia();

        $this->accion('aprobar')->assertRedirect()->assertSessionHasNoErrors();
        $this->administracion();
        $this->accion('enviar')->assertRedirect()->assertInertiaFlash('comisiones.descripcion', 'Se enviarán 2 correos en segundo plano. Esta pantalla se actualiza sola.');

        Mail::assertSent(DetalleComisionAsesor::class, 2);
        foreach ($asesores as $asesor) {
            Mail::assertSent(DetalleComisionAsesor::class, fn (DetalleComisionAsesor $correo): bool => count($correo->to) === 1
                && $correo->hasTo($asesor) && $correo->cc === [] && $correo->bcc === []);
        }
        Mail::assertNothingQueued();
    }

    public function test_un_fallo_individual_no_detiene_los_demas_y_solo_el_fallido_se_reenvia(): void
    {
        $this->administracion();
        Exceptions::fake();
        $intentos = [];
        $fallido = 'alba@ejemplo.test';

        Event::listen(MessageSending::class, function (MessageSending $evento) use (&$intentos, $fallido): void {
            $destinatario = $evento->message->getTo()[0]->getAddress();
            $intentos[$destinatario] = ($intentos[$destinatario] ?? 0) + 1;

            if ($destinatario === $fallido && $intentos[$destinatario] === 1) {
                throw new RuntimeException('Detalle privado del fallo simulado.');
            }
        });

        $this->accion('procesar')->assertRedirect();
        $this->gerencia();
        $this->accion('aprobar')->assertRedirect()->assertSessionHasNoErrors();
        $this->administracion();
        $this->accion('enviar')->assertRedirect()
            ->assertInertiaFlash('comisiones.tipo', 'exito')
            ->assertInertiaFlash('comisiones.titulo', 'Envío al personal en curso');

        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'enviada_personal')
            ->where('liquidacion.envios.0.estado', 'fallido')
            ->where('liquidacion.envios.0.enviado_en', null)
            ->where('liquidacion.envios.0.error', 'No se pudo enviar el correo.')
            ->where('liquidacion.envios.1.estado', 'enviado')
            ->where('permisos.puede_reenviar', true));
        $this->assertCount(2, Mail::getSymfonyTransport()->messages());

        $this->administracion();
        $this->accion('reenviar')->assertRedirect()->assertInertiaFlash('comisiones.descripcion', 'Se enviará 1 correo en segundo plano. Esta pantalla se actualiza sola.');

        $this->assertCount(3, Mail::getSymfonyTransport()->messages());
        $this->assertSame([$fallido => 2], array_filter($intentos, fn (int $cantidad): bool => $cantidad > 1));
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.envios.0.estado', 'enviado')->where('liquidacion.envios.0.error', null)
            ->where('liquidacion.historial.3.tipo', 'reenvio')->where('permisos.puede_reenviar', false));
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_si_falla_el_correo_de_gerencia_conserva_el_borrador_y_puede_reintentar(): void
    {
        $this->administracion();
        Exceptions::fake();
        $intentos = 0;

        Event::listen(MessageSending::class, function () use (&$intentos): void {
            if (++$intentos === 1) {
                throw new RuntimeException('Fallo SMTP simulado.');
            }
        });

        $this->accion('procesar')->assertSessionHasErrors([
            'accion' => 'No se pudo enviar el correo a gerencia. Vuelve a intentarlo.',
        ]);

        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'borrador')->has('liquidacion.historial', 0)
            ->where('permisos.puede_procesar', true));
        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
        Exceptions::assertReported(RuntimeException::class);

        $this->accion('procesar')->assertRedirect()->assertInertiaFlash('comisiones.tipo', 'exito');

        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina->where('liquidacion.estado', 'enviada'));
    }

    public function test_bloquea_el_envio_si_el_mailer_por_defecto_no_es_el_sandbox(): void
    {
        $this->administracion();
        Exceptions::fake();
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);

        $this->accion('procesar')->assertSessionHasErrors('accion');

        Mail::assertNothingOutgoing();
        Exceptions::assertReported(LogicException::class);
    }

    public function test_bloquea_destinatarios_fuera_del_dominio_de_ejemplo(): void
    {
        $this->administracion();
        $detalle = $this->detalle()->assertOk()->viewData('page')['props']['liquidacion'];
        $detalle['correo_gerencia'] = 'gerencia@example.com';
        Mail::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Los correos de comisiones solo admiten destinatarios de ejemplo.test.');

        (new EnviarCorreosComisiones)->gerencia($detalle, 'Ana Operaciones', '2026-11-03T09:15:00-05:00');
    }
}
