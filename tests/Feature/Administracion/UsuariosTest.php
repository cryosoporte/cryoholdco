<?php

namespace Tests\Feature\Administracion;

use App\Administracion\ListadoUsuarios;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_lista_una_persona_por_dni_y_marca_la_cuenta_en_sesion_de_la_otra_empresa(): void
    {
        $this->prepararUsuarios();
        $soporte = $this->crearPersonaUsuario('Soporte', '001234567', [
            '001' => ' Soporte@ejemplo.test ',
            '002' => 'soporte@ejemplo.test',
        ]);
        foreach ($soporte as $cuenta) {
            $this->asignarRol($cuenta->id, 'ADMINISTRADOR');
        }
        $persona = $this->crearPersonaUsuario('Andrea Ejemplo', ' 12345678 ', [
            '001' => 'andrea@empresa-uno.test',
            '002' => 'Andrea@empresa-dos.test',
        ]);
        $persona['002']->forceFill(['identificacion' => '12345678'])->save();

        $this->actingAs($soporte['002'])->get(route('administracion.usuarios'))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->component('administracion-usuarios')
                ->has('personas', 2)
                ->where('personas.0', [
                    'id' => $soporte['001']->id,
                    'nombre' => 'Soporte',
                    'correos' => ['soporte@ejemplo.test'],
                    'dni' => '001234567',
                    'cuentas' => 2,
                    'roles' => ['ADMINISTRADOR'],
                    'roles_incompletos' => false,
                    'es_usted' => true,
                ])
                ->where('personas.1', [
                    'id' => $persona['001']->id,
                    'nombre' => 'Andrea Ejemplo',
                    'correos' => ['andrea@empresa-uno.test', 'andrea@empresa-dos.test'],
                    'dni' => '12345678',
                    'cuentas' => 2,
                    'roles' => [],
                    'roles_incompletos' => false,
                    'es_usted' => false,
                ]));

        $this->assertSame($persona->pluck('id')->values()->all(), $persona['002']->filasDeLaPersona()->modelKeys());
    }

    public function test_agrupa_por_correo_normalizado_solo_las_cuentas_sin_dni(): void
    {
        $this->prepararUsuarios();
        $sinDni = $this->crearPersonaUsuario('Área de soporte', null, [
            '001' => '  ÁREA@ejemplo.test ',
            '002' => 'área@ejemplo.test',
        ]);
        $sinDni['002']->forceFill(['identificacion' => '   '])->save();
        $this->asignarRol($sinDni['001']->id, 'ADMINISTRADOR');
        $conDni = $this->crearPersonaUsuario('Otra persona', '87654321', ['001' => 'área@ejemplo.test']);

        $this->actingAs($sinDni['001'])->get(route('administracion.usuarios'))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->has('personas', 2)
                ->where('personas.0.id', $sinDni['001']->id)
                ->where('personas.0.dni', '')
                ->where('personas.0.correos', ['área@ejemplo.test'])
                ->where('personas.0.cuentas', 2)
                ->where('personas.0.es_usted', true)
                ->where('personas.1.id', $conDni['001']->id)
                ->where('personas.1.es_usted', false));

        $this->assertSame($sinDni->pluck('id')->values()->all(), $sinDni['001']->filasDeLaPersona()->modelKeys());
    }

    public function test_distingue_roles_incompletos_y_ordena_por_grupo_y_nombre_con_dos_consultas(): void
    {
        $this->prepararUsuarios();
        $administrador = $this->crearPersonaUsuario('Zeta Administrador', '11111111', ['001' => 'administrador@ejemplo.test']);
        $gerencia = $this->crearPersonaUsuario('Zeta Gerencia', '22222222', [
            '001' => 'gerencia@ejemplo.test',
            '002' => 'gerencia@ejemplo.test',
        ]);
        $dosRoles = $this->crearPersonaUsuario('beta Dos roles', '33333333', [
            '001' => 'dos-roles@ejemplo.test',
            '002' => 'dos-roles@ejemplo.test',
        ]);
        $rolParcial = $this->crearPersonaUsuario('Alfa Rol parcial', '44444444', [
            '001' => 'parcial@ejemplo.test',
            '002' => 'parcial@ejemplo.test',
        ]);
        $rolesCoincidentes = $this->crearPersonaUsuario('Gamma Dos roles completos', '77777777', [
            '001' => 'completo@ejemplo.test',
            '002' => 'completo@ejemplo.test',
        ]);
        $this->crearPersonaUsuario('Zeta Sin rol', '55555555', ['001' => 'zeta@ejemplo.test']);
        $this->crearPersonaUsuario('alfa Sin rol', '66666666', ['001' => 'alfa@ejemplo.test']);

        $this->asignarRol($administrador['001']->id, 'ADMINISTRADOR');
        $this->asignarRol($gerencia['001']->id, 'GERENCIA');
        $this->asignarRol($gerencia['002']->id, 'GERENCIA');
        $this->asignarRol($dosRoles['001']->id, 'GERENCIA');
        $this->asignarRol($dosRoles['002']->id, 'GERENCIA');
        $this->asignarRol($dosRoles['002']->id, 'ADMINISTRADOR');
        $this->asignarRol($rolParcial['002']->id, 'GERENCIA');
        $this->asignarRol($rolesCoincidentes['001']->id, 'GERENCIA');
        $this->asignarRol($rolesCoincidentes['001']->id, 'ADMINISTRADOR');
        $this->asignarRol($rolesCoincidentes['002']->id, 'ADMINISTRADOR');
        $this->asignarRol($rolesCoincidentes['002']->id, 'GERENCIA');

        $this->expectsDatabaseQueryCount(2, 'sqlite');
        $personas = app(ListadoUsuarios::class)->consultar($administrador['001']);

        $this->assertSame([
            ['beta Dos roles', ['ADMINISTRADOR', 'GERENCIA'], true],
            ['Gamma Dos roles completos', ['ADMINISTRADOR', 'GERENCIA'], false],
            ['Zeta Administrador', ['ADMINISTRADOR'], false],
            ['Alfa Rol parcial', ['GERENCIA'], true],
            ['Zeta Gerencia', ['GERENCIA'], false],
            ['alfa Sin rol', [], false],
            ['Zeta Sin rol', [], false],
        ], $personas->map(fn (array $persona): array => [
            $persona['nombre'],
            $persona['roles'],
            $persona['roles_incompletos'],
        ])->all());
    }

    public function test_usuarios_redirige_a_login_sin_sesion(): void
    {
        $this->get(route('administracion.usuarios'))->assertRedirectToRoute('login');
        $this->put(route('administracion.usuarios.roles', 1), ['roles' => []])->assertRedirectToRoute('login');
    }

    public function test_usuarios_recibe_los_roles_disponibles_en_la_base(): void
    {
        $this->prepararUsuarios();
        $this->crearRol('AUDITORIA', ['comisiones.ver']);
        $usuario = $this->crearPersonaUsuario('Administrador', '12345678', ['001' => 'admin@ejemplo.test'])->first();
        $this->asignarRol($usuario->id, 'ADMINISTRADOR');

        $this->actingAs($usuario)->get(route('administracion.usuarios'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->where('rolesDisponibles', [
                    ['codigo' => 'ADMINISTRADOR', 'nombre' => 'Administrador', 'descripcion' => null],
                    ['codigo' => 'AUDITORIA', 'nombre' => 'AUDITORIA', 'descripcion' => null],
                    ['codigo' => 'GERENCIA', 'nombre' => 'Gerencia', 'descripcion' => null],
                ]));
    }
}
