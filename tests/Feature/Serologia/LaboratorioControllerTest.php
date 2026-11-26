<?php

namespace Tests\Feature\Serologia;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaboratorioControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSerologia();
    }

    public function test_abre_el_mostrador_sin_fallar_cuando_no_hay_sedes(): void
    {
        $this->get(route('laboratorio.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
<<<<<<< Updated upstream
            ->component('laboratorio')->where('laboratorio', null)->where('sede', null)
            ->where('hoy', [])->where('resultado', null));
=======
            ->component('laboratorio')
            ->where('laboratorio', '20330025213')
            ->where('nombre_laboratorio', 'Laboratorio de prueba')
            ->where('sedes', [['codigo' => '01', 'nombre' => 'Sede 01', 'direccion' => null, 'ciudad' => 'LIMA']])
            ->where('sede', '01')
            ->where('nombre_sede', 'Sede 01')
            ->missing('laboratorios')
            ->where('hoy', [[
                'numero_contrato' => 'PE-CR-2026-000001-0', 'numero_toma' => 1,
                'nombre' => 'Ana Ejemplo', 'hora' => '09:15',
            ]])
            ->where('resultado', null));
    }

    public function test_sin_sesion_el_mostrador_lleva_al_login_del_laboratorio(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('laboratorio.index'))->assertRedirectToRoute('laboratorio.login');
        $this->post(route('laboratorio.consultar'), ['codigo' => '12345678'])->assertRedirectToRoute('laboratorio.login');
        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])->assertRedirectToRoute('laboratorio.login');
        $this->post(route('laboratorio.sede'), ['sede' => '01'])->assertRedirectToRoute('laboratorio.login');
        $this->get(route('laboratorio.sede.elegir'))->assertRedirectToRoute('laboratorio.login');
    }

    public function test_un_usuario_interno_no_entra_al_mostrador(): void
    {
        $this->app['auth']->forgetGuards();

        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']), 'web')
            ->get(route('laboratorio.index'))
            ->assertRedirectToRoute('laboratorio.login');
    }

    public function test_saca_al_laboratorio_si_desactivan_su_acceso(): void
    {
        $this->acceso->update(['estado' => UsuarioLaboratorio::ESTADO_INACTIVO]);

        $this->get(route('laboratorio.index'))
            ->assertRedirectToRoute('laboratorio.login')
            ->assertSessionHasErrors(['ruc' => 'Tu acceso no está habilitado. Comunícate con Criocord.']);

        $this->assertGuest('laboratorio');
    }

    public function test_no_registra_la_toma_si_el_laboratorio_se_quedo_sin_sedes_activas(): void
    {
        $this->crearContratoPrueba();
        DB::table('lab_laboratorios_sedes')->update(['estado' => 'I']);

        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertRedirectToRoute('laboratorio.login')
            ->assertSessionHasErrors(['ruc' => 'Tu acceso no está habilitado. Comunícate con Criocord.']);

        $this->assertGuest('laboratorio');
        $this->assertDatabaseHas('ope_tomas_serologicas', ['numero_toma' => 1, 'fecha_toma' => null]);
>>>>>>> Stashed changes
    }

    public function test_rechaza_la_busqueda_sin_codigo(): void
    {
        $this->from(route('laboratorio.index'))->post(route('laboratorio.consultar'), [])
            ->assertSessionHasErrors(['codigo' => 'Escanea el QR o escribe el código o el DNI.']);
    }

    public function test_informa_cuando_el_contrato_no_existe(): void
    {
        $this->post(route('laboratorio.consultar'), ['codigo' => 'INEXISTENTE'])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHasErrors(['codigo' => 'No es clienta de Criocord, o el código está mal escrito. Prueba con el DNI.']);
    }

    #[DataProvider('serviciosConSerologia')]
    public function test_solo_encuentra_contratos_de_servicios_con_serologia(string $servicio, bool $encontrado): void
    {
        $this->crearContratoPrueba();
        DB::table('ven_contratos')->update(['servicio' => $servicio]);

        $respuesta = $this->post(route('laboratorio.consultar'), ['codigo' => 'PE-CR-2026-000001-0'])
            ->assertRedirectToRoute('laboratorio.index');

        $encontrado
            ? $respuesta->assertSessionHas('consulta_laboratorio.numero_contrato', 'PE-CR-2026-000001-0')
            : $respuesta->assertSessionHasErrors(['codigo' => 'No es clienta de Criocord, o el código está mal escrito. Prueba con el DNI.']);
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

    public function test_impide_tomar_una_muestra_sin_registro(): void
    {
        $this->crearContratoPrueba(conToma: false);
        $this->post(route('laboratorio.consultar'), ['codigo' => '12345678'])
            ->assertSessionHasErrors(['codigo' => 'Este contrato todavía no está cargado en el sistema de tomas. No tomes la muestra: avisa a Criocord.']);
    }

    public function test_busca_por_qr_y_entrega_solo_los_datos_del_mostrador(): void
    {
        $this->crearContratoPrueba();
        $this->post(route('laboratorio.consultar'), ['codigo' => 'PE-CR-2026-000001-0'])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHas('consulta_laboratorio', [
                'nombre' => 'Ana Ejemplo', 'primer_nombre' => 'Ana',
                'numero_contrato' => 'PE-CR-2026-000001-0', 'numero_toma' => 1,
                'estado' => 'pendiente', 'fecha_toma' => null, 'hora_toma' => null,
                'sede_toma' => null, 'recien_marcada' => false,
            ]);
    }

    public function test_cambiar_de_laboratorio_selecciona_una_sede_valida_y_limpia_la_consulta(): void
    {
        $this->crearSedePrueba('99999999999', '02');
        $this->withSession(['consulta_laboratorio' => ['nombre' => 'Consulta anterior']])
            ->post(route('laboratorio.sede'), ['laboratorio' => '99999999999', 'sede' => '99'])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHas('laboratorio.ruc', '99999999999')
            ->assertSessionHas('laboratorio.sede', '02')
            ->assertSessionMissing('consulta_laboratorio');
    }

    public function test_no_cambia_la_sesion_si_el_laboratorio_no_tiene_sedes(): void
    {
        $this->withSession(['laboratorio.ruc' => '20330025213', 'laboratorio.sede' => '01'])
            ->post(route('laboratorio.sede'), ['laboratorio' => 'INEXISTENTE'])
            ->assertSessionHasErrors(['laboratorio' => 'Ese laboratorio no tiene sedes activas.'])
            ->assertSessionHas('laboratorio.ruc', '20330025213')
            ->assertSessionHas('laboratorio.sede', '01');
    }

    public function test_no_registra_la_toma_si_no_hay_sede(): void
    {
        $this->crearContratoPrueba();
        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertSessionHasErrors(['sede' => 'Selecciona un laboratorio y una sede activa antes de registrar la muestra.']);
        $this->assertDatabaseHas('ope_tomas_serologicas', ['fecha_toma' => null]);
    }

    public function test_usa_otro_laboratorio_si_el_elegido_no_tiene_sedes_activas(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba('99999999999', '02');

        $this->withSession(['laboratorio.ruc' => '20330025213'])
            ->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ope_tomas_serologicas', [
            'numero_toma' => 1, 'laboratorio_toma' => '99999999999', 'sede_toma' => '02',
        ]);
    }

    public function test_usa_su_unica_sede_aunque_la_guardada_ya_no_este_activa(): void
    {
        $this->crearContratoPrueba();
<<<<<<< Updated upstream
        $this->crearSedePrueba(sede: '01');
        $this->crearSedePrueba(sede: '02');
=======
>>>>>>> Stashed changes

        $this->withSession(['laboratorio.ruc' => '20330025213', 'laboratorio.sede' => '07'])
            ->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ope_tomas_serologicas', [
            'numero_toma' => 1, 'laboratorio_toma' => '20330025213', 'sede_toma' => '01',
        ]);
    }

    public function test_con_una_sola_sede_entra_directo_al_mostrador(): void
    {
        $this->simularSysdateOracle('2026-11-10 08:00:00');

        $this->get(route('laboratorio.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('laboratorio')
            ->where('sede', '01'));
    }

    public function test_con_varias_sedes_el_mostrador_pide_elegir_una(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba(sede: '02');

        $this->get(route('laboratorio.index'))->assertRedirectToRoute('laboratorio.sede.elegir');
        $this->post(route('laboratorio.consultar'), ['codigo' => 'PE-CR-2026-000001-0'])
            ->assertRedirectToRoute('laboratorio.sede.elegir');
        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertRedirectToRoute('laboratorio.sede.elegir');

        $this->assertDatabaseHas('ope_tomas_serologicas', ['numero_toma' => 1, 'fecha_toma' => null]);
    }

    public function test_la_pantalla_de_eleccion_trae_las_sedes_activas_del_laboratorio(): void
    {
        $this->crearSedePrueba(sede: '02');
        $this->crearSedePrueba('20396499691', '05');

        $this->get(route('laboratorio.sede.elegir'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('laboratorio-sede')
            ->where('laboratorio', '20330025213')
            ->where('nombre_laboratorio', 'Laboratorio de prueba')
            ->where('sede', null)
            ->where('sedes', [
                ['codigo' => '01', 'nombre' => 'Sede 01', 'direccion' => null, 'ciudad' => 'LIMA'],
                ['codigo' => '02', 'nombre' => 'Sede 02', 'direccion' => null, 'ciudad' => 'LIMA'],
            ]));
    }

    public function test_al_elegir_la_sede_entra_al_mostrador(): void
    {
        $this->crearSedePrueba(sede: '02');
        $this->simularSysdateOracle('2026-11-10 08:00:00');

        $this->post(route('laboratorio.sede'), ['sede' => '02'])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHas('laboratorio.sede', '02');

        $this->get(route('laboratorio.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('laboratorio')
            ->where('sede', '02'));
    }

    public function test_la_sede_que_no_es_suya_lo_devuelve_a_elegir(): void
    {
        $this->crearSedePrueba(sede: '02');
        $this->crearSedePrueba('20396499691', '05');

        $this->post(route('laboratorio.sede'), ['sede' => '05'])
            ->assertRedirectToRoute('laboratorio.sede.elegir')
            ->assertSessionHasErrors(['sede' => 'Esa sede no está activa para tu laboratorio.'])
            ->assertSessionMissing('laboratorio.sede');
    }

    public function test_vuelve_a_pedir_la_sede_si_la_elegida_deja_de_estar_activa(): void
    {
        $this->crearSedePrueba(sede: '02');
        $this->crearSedePrueba(sede: '03');
        $this->simularSysdateOracle('2026-11-10 08:00:00');

        $this->withSession(['laboratorio.sede' => '02'])
            ->get(route('laboratorio.index'))->assertOk();

        DB::table('lab_laboratorios_sedes')->where('codigo_sede', '02')->update(['estado' => 'I']);

        $this->withSession(['laboratorio.sede' => '02'])
            ->get(route('laboratorio.index'))->assertRedirectToRoute('laboratorio.sede.elegir');
    }

    public function test_registra_la_muestra_y_no_sobrescribe_una_toma_repetida(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba();
        $this->travelTo('2026-11-10 10:30:00');

        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertRedirectToRoute('laboratorio.index')->assertSessionHasNoErrors()
            ->assertSessionHas('consulta_laboratorio.estado', 'tomada')
            ->assertSessionHas('consulta_laboratorio.recien_marcada', true);
        $this->assertDatabaseHas('ope_tomas_serologicas', [
            'numero_contrato_base' => 'PE-CR-2026-000001-0', 'fecha_toma' => '2026-11-10 10:30:00',
            'laboratorio_toma' => '20330025213', 'sede_toma' => '01',
        ]);

        $this->travelTo('2026-11-10 11:00:00');
        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertSessionHas('consulta_laboratorio.estado', 'tomada')
            ->assertSessionHas('consulta_laboratorio.hora_toma', '10:30')
            ->assertSessionHas('consulta_laboratorio.recien_marcada', false);
        $this->assertDatabaseHas('ope_tomas_serologicas', ['fecha_toma' => '2026-11-10 10:30:00']);
    }

    public function test_repetir_el_registro_no_marca_la_siguiente_toma_pendiente(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba();
        DB::table('ope_tomas_serologicas')->insert([
            'no_cia' => '001', 'numero_contrato_base' => 'PE-CR-2026-000001-0', 'numero_toma' => 2,
        ]);

        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('consulta_laboratorio.recien_marcada', true);
        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('consulta_laboratorio.recien_marcada', false);
        $this->assertDatabaseHas('ope_tomas_serologicas', ['numero_toma' => 2, 'fecha_toma' => null]);
    }

    public function test_rechaza_el_registro_sin_identificar_la_toma(): void
    {
        $this->from(route('laboratorio.index'))->post(route('laboratorio.marcar'), ['codigo' => '12345678'])
            ->assertSessionHasErrors(['numero_toma' => 'Vuelve a buscar el contrato antes de registrar la muestra.']);
    }

    public function test_rechaza_el_registro_sin_codigo_de_contrato(): void
    {
        $this->from(route('laboratorio.index'))->post(route('laboratorio.marcar'), ['numero_toma' => 1])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHasErrors(['codigo' => 'Falta el código del contrato.']);
    }

    public function test_rechaza_un_codigo_de_mas_de_40_caracteres(): void
    {
        $this->from(route('laboratorio.index'))->post(route('laboratorio.consultar'), ['codigo' => str_repeat('A', 41)])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHasErrors('codigo');
    }

    public function test_no_cambia_la_sede_sin_indicar_el_laboratorio(): void
    {
        $this->withSession(['laboratorio.ruc' => '20330025213', 'laboratorio.sede' => '01'])
            ->from(route('laboratorio.index'))->post(route('laboratorio.sede'), ['sede' => '01'])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHasErrors('laboratorio')
            ->assertSessionHas('laboratorio.ruc', '20330025213');
    }

    public function test_no_registra_una_toma_que_no_pertenece_al_contrato(): void
    {
        $this->crearContratoPrueba();
        $this->crearSedePrueba();

        $this->post(route('laboratorio.marcar'), ['codigo' => '12345678', 'numero_toma' => 99])
            ->assertSessionHasErrors(['codigo' => 'No encontramos esa toma. Vuelve a escanear.']);
        $this->assertDatabaseHas('ope_tomas_serologicas', ['numero_toma' => 1, 'fecha_toma' => null]);
    }
}
