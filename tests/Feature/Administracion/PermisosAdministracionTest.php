<?php

namespace Tests\Feature\Administracion;

use App\Administracion\RolSistema;
use App\Models\UsuarioLaboratorio;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PermisosAdministracionTest extends TestCase
{
    /** @param list<string> $rutasReportes */
    #[TestWith([null, ['reportes.fuentes']])]
    #[TestWith([RolSistema::Gerencia, ['reportes.comisiones', 'reportes.fuentes']])]
    public function test_sin_permiso_se_oculta_administracion_y_todas_sus_rutas_responden_403(?RolSistema $rol, array $rutasReportes): void
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

            $parametros = array_intersect_key(['acceso' => $acceso->id, 'usuario' => $persona->id], array_flip($ruta->parameterNames()));
            $this->call($ruta->methods()[0], route($ruta->getName(), $parametros), [
                'estado' => UsuarioLaboratorio::ESTADO_INACTIVO,
                'rol' => RolSistema::Administrador->value,
            ])->assertForbidden();
        }

        $this->put(route('administracion.laboratorios.actualizar', $acceso->id + 1))->assertForbidden();
        $this->put(route('administracion.usuarios.rol', $persona->id + 1), ['rol' => null])->assertForbidden();
        $this->assertDatabaseMissing('usuario_rol', ['user_id' => $persona->id]);
        $this->assertDatabaseCount($acceso->getTable(), 1);
        $this->assertSame(UsuarioLaboratorio::ESTADO_ACTIVO, $acceso->fresh()->estado);
    }

    public function test_administrador_puede_abrir_administracion_y_laboratorios_y_recibe_su_acceso(): void
    {
        $this->prepararSerologia();
        $this->asignarRol(1, RolSistema::Administrador);
        $this->actingAs(UserFactory::new()->make(['id' => 1]));

        $this->get(route('administracion'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('administracion')
            ->where('rutasAdministracion', ['administracion.laboratorios', 'administracion.usuarios']));
        $this->get(route('administracion.laboratorios'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('administracion-laboratorios')
            ->where('rutasAdministracion', ['administracion.laboratorios', 'administracion.usuarios']));
    }

    #[TestWith([null, false])]
    #[TestWith([RolSistema::Gerencia, false])]
    #[TestWith([RolSistema::Administrador, true])]
    public function test_solo_el_rol_administrador_tiene_permiso_para_ver_administracion(?RolSistema $rol, bool $permitido): void
    {
        $usuario = UserFactory::new()->make(['id' => 1]);
        if ($rol !== null) {
            $this->asignarRol(1, $rol);
        }

        $this->assertSame($permitido, $usuario->can('administracion.ver'));
    }
}
