<?php

namespace Tests\Feature\Serologia;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClienteControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSerologia();
    }

    public function test_muestra_el_formulario_publico_sin_datos_de_cliente(): void
    {
        $this->get(route('clientes.index'))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('clientes')->where('cliente', null));
    }

    #[DataProvider('dnisInvalidos')]
    public function test_rechaza_el_dni_invalido_con_el_mensaje_del_formulario(?string $dni, string $mensaje): void
    {
        $this->from(route('clientes.index'))->post(route('clientes.consultar'), ['dni' => $dni])
            ->assertRedirectToRoute('clientes.index')
            ->assertSessionHasErrors(['dni' => $mensaje]);
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function dnisInvalidos(): array
    {
        return [
            'vacio' => [null, 'Escribe tu DNI.'],
            'corto' => ['123', 'El DNI debe tener 8 dígitos.'],
            'letras' => ['abcdefgh', 'El DNI debe tener 8 dígitos.'],
        ];
    }

    public function test_informa_cuando_el_dni_no_tiene_un_contrato(): void
    {
        $this->post(route('clientes.consultar'), ['dni' => '12345678'])
            ->assertRedirectToRoute('clientes.index')
            ->assertSessionHasErrors(['dni' => 'No encontramos ese DNI. Revísalo o escríbenos por WhatsApp.']);
    }

    #[DataProvider('serviciosConSerologia')]
    public function test_solo_encuentra_contratos_de_servicios_con_serologia(string $servicio, bool $encontrado): void
    {
        $this->crearContratoPrueba();
        DB::table('ven_contratos')->update(['servicio' => $servicio]);

        $respuesta = $this->post(route('clientes.consultar'), ['dni' => '12345678'])
            ->assertRedirectToRoute('clientes.index');

        $encontrado
            ? $respuesta->assertSessionHas('consulta_cliente.dni', '12345678')
            : $respuesta->assertSessionHasErrors(['dni' => 'No encontramos ese DNI. Revísalo o escríbenos por WhatsApp.']);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function serviciosConSerologia(): array
    {
        return [
            'sangre' => ['S', true],
            'tejido' => ['T', true],
            'otro servicio' => ['X', false],
        ];
    }

    public function test_entrega_la_ficha_y_sedes_como_props_sin_exponer_el_modelo_completo(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba();

        $this->post(route('clientes.consultar'), ['dni' => '12345678'])
            ->assertRedirectToRoute('clientes.index')->assertSessionHasNoErrors();
        $this->get(route('clientes.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('clientes')
            ->where('cliente.dni', '12345678')
            ->where('cliente.numero_contrato', 'PE-CR-2026-000001-0')
            ->where('cliente.nombre', 'Ana Ejemplo')
            ->where('cliente.estado', 'pendiente')
            ->where('cliente.fecha_contrato', '2026-10-01')
            ->where('cliente.sedes.0.nombre', 'Sede 01')
            ->missing('cliente.no_cia')
            ->missing('cliente.identificacion1'));

        $this->get(route('clientes.index'))->assertInertia(fn (Assert $page): Assert => $page->where('cliente', null));
    }

    public function test_distingue_un_contrato_sin_registro_de_una_toma_pendiente(): void
    {
        $this->crearContratoPrueba(conToma: false);
        $this->post(route('clientes.consultar'), ['dni' => '12345678'])
            ->assertSessionHas('consulta_cliente.estado', 'sin_registro');
    }

    public function test_conserva_las_fechas_y_la_sede_de_un_resultado_recibido(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba();
        DB::table('ope_tomas_serologicas')->update([
            'fecha_toma' => '2026-11-09 10:30:00', 'fecha_resultado' => '2026-11-10 12:00:00',
            'laboratorio_toma' => '20330025213', 'sede_toma' => '01',
        ]);

        $this->post(route('clientes.consultar'), ['dni' => '12345678'])
            ->assertSessionHas('consulta_cliente.estado', 'recibido')
            ->assertSessionHas('consulta_cliente.fecha_toma', '2026-11-09')
            ->assertSessionHas('consulta_cliente.fecha_resultado', '2026-11-10')
            ->assertSessionHas('consulta_cliente.sede_toma', 'Laboratorio de prueba · Sede 01');
    }
}
