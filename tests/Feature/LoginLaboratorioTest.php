<?php

namespace Tests\Feature;

use App\Models\UsuarioLaboratorio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginLaboratorioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSerologia();
    }

    private function crearAcceso(string $estado = 'A'): UsuarioLaboratorio
    {
        $this->crearSedePrueba('20330025213', '01');

        return UsuarioLaboratorio::factory()->create([
            'identificacion' => '20330025213',
            'password' => 'secreto123',
            'estado' => $estado,
        ]);
    }

    public function test_muestra_la_pantalla_de_login(): void
    {
        $this->get(route('laboratorio.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('laboratorio-login'));
    }

    public function test_ingresa_con_el_ruc_y_la_contrasena(): void
    {
        $acceso = $this->crearAcceso();

        $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'secreto123'])
            ->assertRedirectToRoute('laboratorio.index')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($acceso, 'laboratorio');
        $this->assertGuest('web');
    }

    /**
     * @param  array<string, string>  $credenciales
     */
    #[DataProvider('credencialesIncorrectas')]
    public function test_rechaza_credenciales_incorrectas_con_el_mismo_mensaje(array $credenciales): void
    {
        $this->crearAcceso();

        $this->from(route('laboratorio.login'))
            ->post(route('laboratorio.login.store'), $credenciales)
            ->assertRedirectToRoute('laboratorio.login')
            ->assertSessionHasErrors(['ruc' => 'RUC o contraseña incorrectos.'])
            ->assertSessionHasInput('ruc')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest('laboratorio');
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function credencialesIncorrectas(): array
    {
        return [
            'contraseña incorrecta' => [['ruc' => '20330025213', 'password' => 'equivocada']],
            'RUC sin acceso' => [['ruc' => '20999999999', 'password' => 'secreto123']],
        ];
    }

    public function test_rechaza_un_acceso_inactivo(): void
    {
        $this->crearAcceso('I');

        $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'secreto123'])
            ->assertRedirectToRoute('laboratorio.login')
            ->assertSessionHasErrors(['ruc' => 'Tu acceso no está habilitado. Comunícate con Criocord.']);

        $this->assertGuest('laboratorio');
    }

    public function test_rechaza_un_laboratorio_sin_sedes_activas(): void
    {
        $this->crearAcceso();
        DB::table('lab_laboratorios_sedes')->update(['estado' => 'I']);

        $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'secreto123'])
            ->assertSessionHasErrors(['ruc' => 'Tu acceso no está habilitado. Comunícate con Criocord.']);

        $this->assertGuest('laboratorio');
    }

    public function test_rechaza_el_formulario_vacio(): void
    {
        $this->from(route('laboratorio.login'))
            ->post(route('laboratorio.login.store'), ['ruc' => '', 'password' => ''])
            ->assertRedirectToRoute('laboratorio.login')
            ->assertSessionHasErrors([
                'ruc' => 'Escribe el RUC del laboratorio.',
                'password' => 'Escribe la contraseña.',
            ]);
    }

    public function test_bloquea_el_ruc_tras_cinco_intentos_fallidos(): void
    {
        $this->crearAcceso();

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'equivocada'])
                ->assertSessionHasErrors(['ruc' => 'RUC o contraseña incorrectos.']);
        }

        $respuesta = $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'secreto123']);

        $respuesta->assertSessionHasErrors('ruc');
        $this->assertStringStartsWith('Demasiados intentos.', session('errors')->first('ruc'));
        $this->assertGuest('laboratorio');
    }

    public function test_un_ingreso_correcto_reinicia_los_intentos(): void
    {
        $this->crearAcceso();

        for ($intento = 1; $intento <= 4; $intento++) {
            $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'equivocada']);
        }

        $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'secreto123'])
            ->assertRedirectToRoute('laboratorio.index');
        Auth::guard('laboratorio')->logout();

        for ($intento = 1; $intento <= 4; $intento++) {
            $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'equivocada'])
                ->assertSessionHasErrors(['ruc' => 'RUC o contraseña incorrectos.']);
        }
    }

    public function test_si_ya_inicio_sesion_el_login_lo_lleva_al_mostrador(): void
    {
        $acceso = $this->crearAcceso();

        $this->actingAs($acceso, 'laboratorio')
            ->get(route('laboratorio.login'))
            ->assertRedirectToRoute('laboratorio.index');
    }

    public function test_cierra_la_sesion_del_laboratorio_sin_cerrar_la_del_panel_interno(): void
    {
        $acceso = $this->crearAcceso();
        $sesionInterna = Auth::guard('web')->getName();

        $this->actingAs($acceso, 'laboratorio')
            ->withSession([
                $sesionInterna => 1,
                'laboratorio' => ['sede' => '01'],
                'consulta_laboratorio' => ['numero_contrato' => 'PE-CR-2026-000001-0'],
            ])
            ->post(route('laboratorio.logout'))
            ->assertRedirectToRoute('laboratorio.login')
            ->assertSessionHas($sesionInterna, 1)
            ->assertSessionMissing('laboratorio')
            ->assertSessionMissing('consulta_laboratorio');

        $this->assertGuest('laboratorio');
    }

    public function test_cerrar_sesion_sin_haber_ingresado_lleva_al_login_del_laboratorio(): void
    {
        $this->post(route('laboratorio.logout'))->assertRedirectToRoute('laboratorio.login');
    }

    public function test_la_sesion_del_laboratorio_no_da_acceso_al_panel_interno(): void
    {
        $this->crearAcceso();

        $this->post(route('laboratorio.login.store'), ['ruc' => '20330025213', 'password' => 'secreto123'])
            ->assertRedirectToRoute('laboratorio.index');

        $this->get(route('home'))->assertRedirectToRoute('login');
        $this->get(route('serologia'))->assertRedirectToRoute('login');
        $this->get(route('administracion'))->assertRedirectToRoute('login');
    }
}
