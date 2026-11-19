<?php

namespace Tests\Feature\Reportes;

use App\Reportes\Comisiones\EnviarComisionesDemo;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class ComisionesDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_historial_y_detalle_exponen_las_props_de_la_demo(): void
    {
        $this->iniciarDemo();

        $this->get(route('reportes.comisiones'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina->component('comisiones')
                ->where('rol', 'analista')->has('fuente')->has('periodos_disponibles', 4)
                ->has('liquidaciones', 3)->where('liquidaciones.0.id', '2026-10'));

        $this->detalle()->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('comisiones-liquidacion')
            ->where('rol', 'analista')->has('fuente')->where('liquidacion.estado', 'borrador')
            ->where('permisos', [
                'puede_procesar' => true, 'puede_aprobar' => false,
                'puede_rechazar' => false, 'puede_reenviar' => false,
            ])
            ->has('liquidacion.asesores', 13));
    }

    public function test_las_pantallas_acciones_y_vistas_previas_requieren_sesion(): void
    {
        $this->get(route('reportes.comisiones'))->assertRedirectToRoute('login');
        $this->detalle()->assertRedirectToRoute('login');
        $this->post(route('reportes.comisiones.demo.rol'), ['rol' => 'gerenta'])->assertRedirectToRoute('login');
        $this->post(route('reportes.comisiones.demo.reiniciar'))->assertRedirectToRoute('login');
        $this->post(route('reportes.comisiones.liquidaciones.nueva'), ['periodo' => '2026-10'])->assertRedirectToRoute('login');

        foreach (['procesar', 'aprobar', 'rechazar', 'reenviar'] as $accion) {
            $this->accion($accion)->assertRedirectToRoute('login');
        }

        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2026-10'))->assertRedirectToRoute('login');
        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', 'STECH']))->assertRedirectToRoute('login');
    }

    public function test_rechaza_ids_desconocidos_y_las_liquidaciones_historicas_son_de_solo_lectura(): void
    {
        $this->iniciarDemo();

        $this->detalle('2040-01')->assertNotFound();
        $this->detalle('octubre')->assertNotFound();
        $this->accion('procesar', '2040-01')->assertNotFound();
        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2040-01'))->assertNotFound();
        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', 'INEXISTENTE']))->assertNotFound();
        $this->accion('procesar', '2026-09')->assertSessionHasErrors('accion');
        $this->detalle('2026-09')->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.es_demo_activa', false)->where('liquidacion.estado', 'enviada_personal')
            ->where('permisos', [
                'puede_procesar' => false, 'puede_aprobar' => false,
                'puede_rechazar' => false, 'puede_reenviar' => false,
            ]));
    }

    public function test_recorre_el_estado_desde_procesar_hasta_rechazar_y_aprobar(): void
    {
        $this->iniciarDemo();
        $this->travelTo(now()->parse('2026-11-03T09:15:00-05:00'));
        $this->mock(EnviarComisionesDemo::class, function (MockInterface $correo): void {
            $correo->shouldReceive('gerencia')->twice();
            $correo->shouldReceive('asesor')->times(13);
            $correo->shouldReceive('pausar')->times(12);
        });

        $this->accion('procesar')->assertRedirect()->assertInertiaFlash('comisiones.tipo', 'exito');
        $this->cambiarRol('gerenta');
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'enviada')->where('permisos.puede_aprobar', true));
        $this->accion('rechazar', datos: ['comentario' => 'Revisar la base de anualidades.'])->assertRedirect()
            ->assertInertiaFlash('comisiones.tipo', 'aviso');
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'rechazada')
            ->where('liquidacion.historial.1', [
                'tipo' => 'rechazada', 'fecha' => '2026-11-03T09:15:00-05:00',
                'por' => 'Ana Operaciones', 'comentario' => 'Revisar la base de anualidades.',
            ]));
        $this->cambiarRol('analista');
        $this->accion('procesar')->assertRedirect();
        $this->cambiarRol('gerenta');
        $this->accion('aprobar')->assertRedirect()->assertInertiaFlash('comisiones.descripcion', '13 de 13 enviados');

        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'enviada_personal')->has('liquidacion.envios', 13)
            ->has('liquidacion.historial', 5)->where('liquidacion.historial.3.tipo', 'aprobada')
            ->where('liquidacion.historial.4.tipo', 'enviada_personal')
            ->where('permisos.puede_reenviar', false));
    }

    public function test_impide_transiciones_invalidas_por_rol_y_estado(): void
    {
        $this->iniciarDemo();
        $this->mock(EnviarComisionesDemo::class, function (MockInterface $correo): void {
            $correo->shouldReceive('gerencia')->once();
            $correo->shouldNotReceive('asesor');
        });

        $this->accion('aprobar')->assertSessionHasErrors('accion');
        $this->accion('reenviar')->assertSessionHasErrors('accion');

        $this->cambiarRol('gerenta');
        $this->accion('procesar')->assertSessionHasErrors('accion');
        $this->accion('aprobar')->assertSessionHasErrors('accion');
        $this->post(route('reportes.comisiones.liquidaciones.nueva'), ['periodo' => '2026-T4'])->assertSessionHasErrors('accion');
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'borrador')->has('liquidacion.historial', 0));

        $this->cambiarRol('analista');
        $this->accion('procesar')->assertRedirect();
        $this->accion('procesar')->assertSessionHasErrors('accion');
        $this->accion('aprobar')->assertSessionHasErrors('accion');
        $this->accion('rechazar', datos: ['comentario' => 'Revisar el consolidado.'])->assertSessionHasErrors('accion');

        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'enviada')->has('liquidacion.historial', 1)->has('liquidacion.envios', 0));
    }

    public function test_valida_rol_periodo_y_comentario_sin_cambiar_la_liquidacion(): void
    {
        $this->iniciarDemo();
        $this->mock(EnviarComisionesDemo::class, fn (MockInterface $correo) => $correo->shouldReceive('gerencia')->once());

        $this->post(route('reportes.comisiones.demo.rol'), ['rol' => 'administrador'])->assertSessionHasErrors('rol');
        $this->post(route('reportes.comisiones.liquidaciones.nueva'), ['periodo' => '2026-08'])->assertSessionHasErrors('periodo');
        $this->accion('procesar')->assertRedirect();
        $this->cambiarRol('gerenta');

        foreach (['', 'Corto', str_repeat('a', 501)] as $comentario) {
            $this->accion('rechazar', datos: ['comentario' => $comentario])->assertSessionHasErrors('comentario');
        }

        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'enviada')->has('liquidacion.historial', 1));
    }

    public function test_generar_un_periodo_y_reiniciar_restauran_el_borrador_correcto(): void
    {
        $this->iniciarDemo();

        $this->post(route('reportes.comisiones.liquidaciones.nueva'), ['periodo' => '2026-T4'])
            ->assertRedirectToRoute('reportes.comisiones.liquidaciones.mostrar', '2026-T4');
        $this->detalle('2026-T4')->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.es_demo_activa', true)->where('liquidacion.estado', 'borrador')
            ->where('liquidacion.base.hasta', '2026-12-31')->where('liquidacion.historial.0.tipo', 'generada'));
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.es_demo_activa', false)->where('permisos.puede_procesar', false));
        $this->cambiarRol('gerenta');

        $this->post(route('reportes.comisiones.demo.reiniciar'))->assertRedirectToRoute('reportes.comisiones')
            ->assertSessionMissing('reportes.comisiones_demo');
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('rol', 'analista')->where('liquidacion.estado', 'borrador')
            ->where('liquidacion.es_demo_activa', true)->has('liquidacion.historial', 0)->has('liquidacion.envios', 0));
    }

    private function iniciarDemo(): void
    {
        $this->prepararFuentes();
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'name' => 'Ana Operaciones', 'empresa' => '001']));
    }

    private function cambiarRol(string $rol): void
    {
        $this->from(route('reportes.comisiones'))
            ->post(route('reportes.comisiones.demo.rol'), compact('rol'))->assertRedirectToRoute('reportes.comisiones');
    }

    private function detalle(string $id = '2026-10'): TestResponse
    {
        return $this->get(route('reportes.comisiones.liquidaciones.mostrar', $id));
    }

    /** @param array<string, string> $datos */
    private function accion(string $accion, string $id = '2026-10', array $datos = []): TestResponse
    {
        return $this->from(route('reportes.comisiones.liquidaciones.mostrar', $id))
            ->post(route("reportes.comisiones.liquidaciones.$accion", $id), $datos);
    }
}
