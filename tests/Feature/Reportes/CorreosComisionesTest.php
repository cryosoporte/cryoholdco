<?php

namespace Tests\Feature\Reportes;

use App\Mail\ComisionesParaAprobacion;
use App\Mail\DetalleComisionAsesor;
use App\Reportes\Comisiones\DatosEjemploComisiones;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreosComisionesTest extends TestCase
{
    public function test_el_correo_de_gerencia_incluye_el_resumen_el_enlace_y_la_version_de_texto(): void
    {
        $datos = new DatosEjemploComisiones;
        $detalle = $datos->liquidacion('2026-10');
        $enlace = route('reportes.comisiones.liquidaciones.mostrar', '2026-10');

        $correo = new ComisionesParaAprobacion($detalle, 'Ana <Operaciones>', '2026-11-03T14:15:00+00:00');

        $correo->assertHasSubject('Comisiones Octubre 2026 · pendientes de su aprobación (S/ 8,187.98)')
            ->assertSeeInHtml('S/ 8,187.98')
            ->assertSeeInHtml('S/ 4,925.00')
            ->assertSeeInHtml('Puntos a revisar')
            ->assertSeeInHtml('href="'.$enlace.'"', false)
            ->assertSeeInHtml('Ana &lt;Operaciones&gt; el 03-11-2026 a las 09:15', false)
            ->assertDontSeeInHtml('Ana <Operaciones>', false)
            ->assertSeeInText('S/ 8,187.98')
            ->assertSeeInText('Revisar y aprobar en el sistema:')
            ->assertSeeInText($enlace);
    }

    public function test_el_correo_del_asesor_incluye_solo_sus_lineas_en_html_y_texto(): void
    {
        $datos = new DatosEjemploComisiones;
        $asesor = $datos->asesor('2026-10', 'STECH');

        $correo = new DetalleComisionAsesor($asesor, 'Octubre 2026', '2026-11-03T09:15:00-05:00');

        $correo->assertHasSubject('Tu comisión de octubre 2026: S/ 1,200.00')
            ->assertSeeInHtml('Hola, Heinrrich:')
            ->assertSeeInHtml('15–16 × S/ 75')
            ->assertSeeInHtml('S/ 1,200.00')
            ->assertDontSeeInHtml('Carolina')
            ->assertDontSeeInHtml('S/ 1,475.00')
            ->assertSeeInText('UCU (contratos) | 16 | 15 | 15–16 × S/ 75 | S/ 1,200.00')
            ->assertDontSeeInText('Carolina')
            ->assertDontSeeInText('S/ 1,475.00');
    }

    public function test_el_correo_sin_comision_muestra_la_variante_en_html_y_texto(): void
    {
        $datos = new DatosEjemploComisiones;
        $asesor = $datos->asesor('2026-10', 'CUBA');

        $correo = new DetalleComisionAsesor($asesor, 'Octubre 2026', '2026-11-03T09:15:00-05:00');

        $correo->assertHasSubject('Tu comisión de octubre 2026: S/ 0.00')
            ->assertSeeInHtml('Este mes no se generó comisión')
            ->assertSeeInHtml('No alcanzó la cuota')
            ->assertSeeInText('Este mes no se generó comisión')
            ->assertSeeInText('Total Octubre 2026: S/ 0.00');
    }

    public function test_las_vistas_previas_devuelven_html_y_no_envian_correos(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'name' => 'Ana Operaciones', 'empresa' => '001']));
        Mail::fake();

        $this->get(route('reportes.comisiones.liquidaciones.correo-gerencia', '2026-T4'))
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Cierre Q4 2026 (trimestral)')
            ->assertSee(route('reportes.comisiones.liquidaciones.mostrar', '2026-T4'));

        $this->get(route('reportes.comisiones.liquidaciones.correo-asesor', ['2026-10', 'STECH']))
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Hola, Heinrrich:')
            ->assertDontSee('Carolina');

        Mail::assertNothingOutgoing();
    }
}
