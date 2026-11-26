<?php

namespace Tests\Feature\Administracion;

use App\Models\User;
use App\Models\UsuarioLaboratorio;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccesoLaboratorioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSerologia();
    }

    private function administrador(): User
    {
        $this->asignarRol(1, 'ADMINISTRADOR');

        return UserFactory::new()->make(['id' => 1, 'empresa' => '001']);
    }

    /**
     * @param  array<string, string>  $datos
     * @return array<string, string>
     */
    private function datosAcceso(array $datos = []): array
    {
        return [
            'identificacion' => '20330025213',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'estado' => 'A',
            ...$datos,
        ];
    }

    public function test_administracion_requiere_iniciar_sesion(): void
    {
        $acceso = UsuarioLaboratorio::factory()->create();

        $this->get(route('administracion'))->assertRedirectToRoute('login');
        $this->get(route('administracion.laboratorios'))->assertRedirectToRoute('login');
        $this->post(route('administracion.laboratorios.crear'), $this->datosAcceso())->assertRedirectToRoute('login');
        $this->put(route('administracion.laboratorios.actualizar', $acceso), ['estado' => 'I'])->assertRedirectToRoute('login');

        $this->assertSame(1, UsuarioLaboratorio::count());
        $this->assertSame('A', $acceso->fresh()->estado);
    }

    public function test_abre_la_portada_de_administracion(): void
    {
        $this->actingAs($this->administrador())->get(route('administracion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('administracion'));
    }

    public function test_lista_los_laboratorios_con_sedes_activas_y_los_que_ya_tienen_acceso(): void
    {
        $this->crearSedePrueba('20330025213', '01');
        $this->crearSedePrueba('20330025213', '02');
        $this->crearSedePrueba('20396499691', '01');
        $this->crearSedePrueba('20304706211', '01');
        $this->crearSedePrueba('20508464071', '01');
        DB::table('lab_laboratorios_sedes')->whereIn('identificacion', ['20304706211', '20508464071'])->update(['estado' => 'I']);
        DB::table('cxp_proveedores')->where('identificacion', '20330025213')->update(['alias' => 'SUIZA LAB']);
        DB::table('cxp_proveedores')->where('identificacion', '20396499691')->update(['alias' => 'ESCALABS']);

        UsuarioLaboratorio::factory()->create(['identificacion' => '20396499691']);
        UsuarioLaboratorio::factory()->inactivo()->create(['identificacion' => '20304706211']);

        $this->actingAs($this->administrador())->get(route('administracion.laboratorios'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('administracion-laboratorios')
                ->has('laboratorios', 3)
                ->where('laboratorios.0.identificacion', '20396499691')
                ->where('laboratorios.0.nombre', 'ESCALABS')
                ->where('laboratorios.0.sedes_activas', 1)
                ->where('laboratorios.0.acceso.estado', 'A')
                ->missing('laboratorios.0.acceso.password')
                ->where('laboratorios.1.identificacion', '20304706211')
                ->where('laboratorios.1.sedes_activas', 0)
                ->where('laboratorios.1.acceso.estado', 'I')
                ->where('laboratorios.2.identificacion', '20330025213')
                ->where('laboratorios.2.nombre', 'SUIZA LAB')
                ->where('laboratorios.2.sedes_activas', 2)
                ->where('laboratorios.2.acceso', null));
    }

    public function test_crea_el_acceso_de_un_laboratorio_con_sedes_activas(): void
    {
        $this->crearSedePrueba();

        $this->actingAs($this->administrador())
            ->post(route('administracion.laboratorios.crear'), $this->datosAcceso())
            ->assertRedirectToRoute('administracion.laboratorios')
            ->assertSessionHasNoErrors();

        $acceso = UsuarioLaboratorio::firstOrFail();

        $this->assertSame('20330025213', $acceso->identificacion);
        $this->assertSame('A', $acceso->estado);
        $this->assertTrue(Hash::check('secreto123', $acceso->password));
    }

    /**
     * @param  array<string, string>  $datos
     */
    #[DataProvider('accesosInvalidos')]
    public function test_rechaza_un_acceso_invalido(array $datos, string $campo, string $mensaje): void
    {
        $this->crearSedePrueba();
        $this->crearSedePrueba('20304706211');
        DB::table('lab_laboratorios_sedes')->where('identificacion', '20304706211')->update(['estado' => 'I']);

        $this->actingAs($this->administrador())
            ->from(route('administracion.laboratorios'))
            ->post(route('administracion.laboratorios.crear'), $this->datosAcceso($datos))
            ->assertRedirectToRoute('administracion.laboratorios')
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->assertSame(0, UsuarioLaboratorio::count());
    }

    /** @return array<string, array{0: array<string, string>, 1: string, 2: string}> */
    public static function accesosInvalidos(): array
    {
        return [
            'sin RUC' => [['identificacion' => ''], 'identificacion', 'Falta el RUC del laboratorio.'],
            'laboratorio sin sedes activas' => [['identificacion' => '20304706211'], 'identificacion', 'Ese laboratorio no tiene sedes activas.'],
            'laboratorio inexistente' => [['identificacion' => '20999999999'], 'identificacion', 'Ese laboratorio no tiene sedes activas.'],
            'contraseña corta' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password', 'La contraseña debe tener al menos 8 caracteres.'],
            'contraseñas distintas' => [['password_confirmation' => 'otra12345'], 'password', 'Las contraseñas no coinciden.'],
            'estado inválido' => [['estado' => 'X'], 'estado', 'Elige si el acceso queda activo o inactivo.'],
        ];
    }

    public function test_no_crea_un_segundo_acceso_para_el_mismo_laboratorio(): void
    {
        $this->crearSedePrueba();
        UsuarioLaboratorio::factory()->create(['identificacion' => '20330025213']);

        $this->actingAs($this->administrador())
            ->post(route('administracion.laboratorios.crear'), $this->datosAcceso())
            ->assertSessionHasErrors(['identificacion' => 'Ese laboratorio ya tiene un acceso. Edítalo para cambiar su contraseña.']);

        $this->assertSame(1, UsuarioLaboratorio::count());
    }

    public function test_cambia_el_estado_y_conserva_la_contrasena_si_viene_vacia(): void
    {
        $acceso = UsuarioLaboratorio::factory()->create(['password' => 'secreto123']);

        $this->actingAs($this->administrador())
            ->put(route('administracion.laboratorios.actualizar', $acceso), [
                'password' => '',
                'password_confirmation' => '',
                'estado' => 'I',
            ])
            ->assertRedirectToRoute('administracion.laboratorios')
            ->assertSessionHasNoErrors();

        $acceso->refresh();
        $this->assertSame('I', $acceso->estado);
        $this->assertTrue(Hash::check('secreto123', $acceso->password));
    }

    public function test_cambia_la_contrasena(): void
    {
        $acceso = UsuarioLaboratorio::factory()->create(['password' => 'secreto123']);

        $this->actingAs($this->administrador())
            ->put(route('administracion.laboratorios.actualizar', $acceso), [
                'password' => 'nueva12345',
                'password_confirmation' => 'nueva12345',
                'estado' => 'A',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nueva12345', $acceso->fresh()->password));
    }

    public function test_rechaza_una_contrasena_nueva_que_no_coincide(): void
    {
        $acceso = UsuarioLaboratorio::factory()->create(['password' => 'secreto123']);

        $this->actingAs($this->administrador())
            ->put(route('administracion.laboratorios.actualizar', $acceso), [
                'password' => 'nueva12345',
                'password_confirmation' => 'distinta123',
                'estado' => 'A',
            ])
            ->assertSessionHasErrors(['password' => 'Las contraseñas no coinciden.']);

        $this->assertTrue(Hash::check('secreto123', $acceso->fresh()->password));
    }

    public function test_responde_404_si_el_acceso_no_existe(): void
    {
        $this->actingAs($this->administrador())
            ->put(route('administracion.laboratorios.actualizar', 999), ['estado' => 'A'])
            ->assertNotFound();
    }
}
