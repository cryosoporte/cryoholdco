<?php

namespace Tests\Feature\Reportes;

use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use App\Reportes\Comisiones\DatosEjemploComisiones;
use App\Reportes\Comisiones\EnviarComisionesDemo;
use Database\Factories\UserFactory;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class EnvioComisionesDemoTest extends TestCase
{
    public function test_procesar_envia_un_correo_a_gerencia(): void
    {
        $this->iniciarDemo();
        Mail::fake();

        $this->accion('procesar')->assertRedirect()->assertInertiaFlash('comisiones.tipo', 'exito');

        Mail::assertSent(ComisionesParaAprobacion::class, 1);
        Mail::assertSent(ComisionesParaAprobacion::class, 'gerencia@ejemplo.test');
        Mail::assertNotSent(DetalleComisionAsesor::class);
    }

    public function test_aprobar_envia_un_correo_individual_a_cada_asesor(): void
    {
        $this->iniciarDemo();
        $asesores = (new DatosEjemploComisiones)->liquidacion('2026-10')['asesores'];
        Mail::fake();
        $this->accion('procesar')->assertRedirect();
        $this->cambiarRol('gerenta');

        $this->accion('aprobar')->assertRedirect()->assertInertiaFlash('comisiones.descripcion', '13 de 13 enviados');

        Mail::assertSent(DetalleComisionAsesor::class, 13);
        foreach ($asesores as $asesor) {
            Mail::assertSent(DetalleComisionAsesor::class, fn (DetalleComisionAsesor $correo): bool => count($correo->to) === 1
                && $correo->hasTo($asesor['correo']) && $correo->cc === [] && $correo->bcc === []);
        }
        Mail::assertNothingQueued();
    }

    public function test_un_fallo_individual_no_detiene_los_demas_y_solo_el_fallido_se_reenvia(): void
    {
        $this->iniciarDemo();
        Exceptions::fake();
        $intentos = [];
        $fallido = 'carolina.vasquez@ejemplo.test';

        Event::listen(MessageSending::class, function (MessageSending $evento) use (&$intentos, $fallido): void {
            $destinatario = $evento->message->getTo()[0]->getAddress();
            $intentos[$destinatario] = ($intentos[$destinatario] ?? 0) + 1;

            if ($destinatario === $fallido && $intentos[$destinatario] === 1) {
                throw new RuntimeException('Detalle privado del fallo simulado.');
            }
        });

        $this->accion('procesar')->assertRedirect();
        $this->cambiarRol('gerenta');
        $this->accion('aprobar')->assertRedirect()
            ->assertInertiaFlash('comisiones.tipo', 'aviso')
            ->assertInertiaFlash('comisiones.descripcion', '12 de 13 enviados · 1 falló, puedes reenviarlo');

        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.estado', 'enviada_personal')
            ->where('liquidacion.envios.1.estado', 'fallido')
            ->where('liquidacion.envios.1.enviado_en', null)
            ->where('liquidacion.envios.1.error', 'No se pudo enviar el correo.')
            ->where('liquidacion.envios.12.estado', 'enviado')
            ->where('permisos.puede_reenviar', true));
        $this->assertCount(13, Mail::getSymfonyTransport()->messages());

        $this->cambiarRol('analista');
        $this->accion('reenviar')->assertRedirect()->assertInertiaFlash('comisiones.descripcion', '13 de 13 enviados');

        $this->assertCount(14, Mail::getSymfonyTransport()->messages());
        $this->assertSame([$fallido => 2], array_filter($intentos, fn (int $cantidad): bool => $cantidad > 1));
        $this->detalle()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('liquidacion.envios.1.estado', 'enviado')->where('liquidacion.envios.1.error', null)
            ->where('liquidacion.historial.3.tipo', 'reenvio')->where('permisos.puede_reenviar', false));
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_si_falla_el_correo_de_gerencia_conserva_el_borrador_y_puede_reintentar(): void
    {
        $this->iniciarDemo();
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
        $this->iniciarDemo();
        Exceptions::fake();
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);

        $this->accion('procesar')->assertSessionHasErrors('accion');

        Mail::assertNothingOutgoing();
        Exceptions::assertReported(LogicException::class);
    }

    public function test_bloquea_destinatarios_fuera_del_dominio_de_ejemplo(): void
    {
        $detalle = (new DatosEjemploComisiones)->liquidacion('2026-10');
        $detalle['correo_gerencia'] = 'gerencia@example.com';
        Mail::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('La demo solo admite destinatarios de ejemplo.test.');

        (new EnviarComisionesDemo)->gerencia($detalle, 'Ana Operaciones', '2026-11-03T09:15:00-05:00');
    }

    private function iniciarDemo(): void
    {
        $this->withoutVite();
        $this->prepararFuentes();
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'name' => 'Ana Operaciones', 'empresa' => '001']));
    }

    private function cambiarRol(string $rol): void
    {
        $this->post(route('reportes.comisiones.demo.rol'), compact('rol'))->assertRedirect();
    }

    private function detalle(): TestResponse
    {
        return $this->get(route('reportes.comisiones.liquidaciones.mostrar', '2026-10'));
    }

    private function accion(string $accion): TestResponse
    {
        return $this->from(route('reportes.comisiones.liquidaciones.mostrar', '2026-10'))
            ->post(route("reportes.comisiones.liquidaciones.$accion", '2026-10'));
    }
}
