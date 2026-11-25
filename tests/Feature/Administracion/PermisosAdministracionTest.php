<?php

namespace Tests\Feature\Administracion;

use App\Administracion\CatalogoPermisos;
use App\Models\UsuarioLaboratorio;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PermisosAdministracionTest extends TestCase
{
    /** @param list<string> $rutasReportes */
    #[TestWith([null, ['reportes.fuentes']])]
    #[TestWith(['GERENCIA', ['reportes.comisiones', 'reportes.fuentes']])]
    public function test_sin_permiso_se_oculta_administracion_y_todas_sus_rutas_responden_403(?string $rol, array $rutasReportes): void
    {
        $this->prepararSerologia();
        $this->prepararUsuarios();
        $acceso = UsuarioLaboratorio::factory()->create();
        $usuario = $this->crearPersonaUsuario('Usuario', '12345678', ['001' => 'usuario@ejemplo.test'])->first();
        $persona = $this->crearPersonaUsuario('Otra persona', '87654321', ['001' => 'otra@ejemplo.test'])->first();
        if ($rol !== null) {
            $this->asignarRol($usuario->id, $rol);
        }
        $this->actingAs($usuario);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('dashboard')
            ->where('rutasAdministracion', [])
            ->where('rutasReportes', $rutasReportes));

        foreach (Route::getRoutes() as $ruta) {
            if (! $ruta->named('administracion*')) {
                continue;
            }

            $parametros = array_intersect_key(
                ['acceso' => $acceso->id, 'usuario' => $persona->id, 'rol' => 'ADMINISTRADOR'],
                array_flip($ruta->parameterNames()),
            );
            $this->call($ruta->methods()[0], route($ruta->getName(), $parametros), [
                'estado' => UsuarioLaboratorio::ESTADO_INACTIVO,
                'roles' => ['ADMINISTRADOR'],
            ])->assertForbidden();
        }

        $this->put(route('administracion.laboratorios.actualizar', $acceso->id + 1))->assertForbidden();
        $this->put(route('administracion.usuarios.roles', $persona->id + 1), ['roles' => []])->assertForbidden();
        $this->assertDatabaseMissing('usuario_rol', ['user_id' => $persona->id]);
        $this->assertDatabaseCount($acceso->getTable(), 1);
        $this->assertSame(UsuarioLaboratorio::ESTADO_ACTIVO, $acceso->fresh()->estado);
    }

    public function test_administrador_puede_abrir_administracion_y_laboratorios_y_recibe_su_acceso(): void
    {
        $this->prepararSerologia();
        $this->asignarRol(1, 'ADMINISTRADOR');
        $this->actingAs(UserFactory::new()->make(['id' => 1]));

        $this->get(route('administracion'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('administracion')
            ->where('rutasAdministracion', ['administracion.laboratorios', 'administracion.usuarios', 'administracion.roles', 'administracion.historial']));
        $this->get(route('administracion.laboratorios'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('administracion-laboratorios')
            ->where('rutasAdministracion', ['administracion.laboratorios', 'administracion.usuarios', 'administracion.roles', 'administracion.historial']));
    }

    #[TestWith([null, false])]
    #[TestWith(['GERENCIA', false])]
    #[TestWith(['ADMINISTRADOR', true])]
    public function test_solo_el_rol_administrador_tiene_permiso_para_ver_administracion(?string $rol, bool $permitido): void
    {
        $usuario = UserFactory::new()->make(['id' => 1]);
        if ($rol !== null) {
            $this->asignarRol(1, $rol);
        }

        $this->assertSame($permitido, $usuario->can('administracion.ver'));
        $this->assertSame($permitido, $usuario->can('administracion.laboratorios'));
        $this->assertSame($permitido, $usuario->can('administracion.usuarios'));
        $this->assertSame($permitido, $usuario->can('administracion.roles'));
    }

    public function test_un_rol_solo_de_laboratorios_ve_su_acceso_y_recibe_403_en_usuarios(): void
    {
        $this->prepararSerologia();
        $this->prepararUsuarios();
        $this->crearRol('LABORATORIOS', ['administracion.laboratorios']);
        $usuario = $this->crearPersonaUsuario('Laboratorios', '12345678', ['001' => 'laboratorio@ejemplo.test'])->first();
        $this->asignarRol($usuario->id, 'LABORATORIOS');

        $this->actingAs($usuario)->get(route('administracion'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->where('roles', ['LABORATORIOS'])
                ->where('permisosEfectivos', ['administracion.laboratorios'])
                ->where('rutasAdministracion', ['administracion.laboratorios']));
        $this->get(route('administracion.laboratorios'))->assertOk();
        $this->get(route('administracion.usuarios'))->assertForbidden();
        $this->put(route('administracion.usuarios.roles', $usuario->id), ['roles' => []])->assertForbidden();
        $this->put(route('administracion.usuarios.roles', $usuario->id + 1), ['roles' => []])->assertForbidden();
    }

    public function test_administrador_tiene_todos_los_permisos_aunque_no_tenga_permisos_guardados(): void
    {
        $usuario = UserFactory::new()->make(['id' => 1]);
        $this->asignarRol(1, 'ADMINISTRADOR');
        DB::table('rol_permiso')->where('rol', 'ADMINISTRADOR')->delete();

        $this->assertSame(array_keys(CatalogoPermisos::PERMISOS), $usuario->codigosPermiso());
        $this->assertTrue($usuario->can('administracion.ver'));
        $this->assertTrue($usuario->can('comisiones.ver_detalle'));
        $this->assertTrue($usuario->can('comisiones.aprobar'));
    }
}
