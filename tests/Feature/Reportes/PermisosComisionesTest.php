<?php

namespace Tests\Feature\Reportes;

use App\Administracion\RolSistema;
use App\Models\UsuarioRol;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PermisosComisionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_sin_rol_se_oculta_comisiones_y_todas_sus_rutas_responden_403(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 1]));
        $this->withSession(['reportes.comisiones_demo' => ['rol' => 'gerenta']]);

        $this->get(route('reportes'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->component('reportes')->where('roles', [])->where('rutasReportes', ['reportes.fuentes']));

        foreach (Route::getRoutes() as $ruta) {
            if (! $ruta->named('reportes.comisiones*')) {
                continue;
            }

            $parametros = array_intersect_key(['liquidacion' => '2026-10', 'asesor' => 'STECH'], array_flip($ruta->parameterNames()));
            $this->call($ruta->methods()[0], route($ruta->getName(), $parametros))->assertForbidden();
        }
    }

    #[TestWith([RolSistema::Administrador])]
    #[TestWith([RolSistema::Gerencia])]
    public function test_ambos_roles_pueden_abrir_el_reporte_y_reciben_su_acceso(RolSistema $rol): void
    {
        $this->prepararComisiones();
        $this->prepararFuentes();
        $this->prepararLiquidaciones();
        $this->asignarRol(1, $rol);
        $this->actingAs(UserFactory::new()->make(['id' => 1]));

        $this->get(route('reportes'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('roles', [$rol->value])->where('rutasReportes', ['reportes.comisiones', 'reportes.fuentes']));
        $this->get(route('reportes.comisiones'))->assertOk();
        $this->get(route('reportes.comisiones.liquidaciones.mostrar', '2026-10'))->assertOk();
    }

    /** @param list<string> $permitidas */
    #[TestWith([null, []])]
    #[TestWith([RolSistema::Administrador, ['ver', 'procesar', 'enviar']])]
    #[TestWith([RolSistema::Gerencia, ['ver', 'aprobar', 'rechazar']])]
    public function test_cada_rol_solo_tiene_los_permisos_de_su_funcion(?RolSistema $rol, array $permitidas): void
    {
        $usuario = UserFactory::new()->make(['id' => 1]);
        if ($rol !== null) {
            $this->asignarRol(1, $rol);
        }

        foreach (['ver', 'procesar', 'aprobar', 'rechazar', 'enviar'] as $accion) {
            $this->assertSame(in_array($accion, $permitidas, true), $usuario->can("comisiones.$accion"), $accion);
        }
    }

    public function test_el_mismo_correo_solo_accede_desde_la_empresa_cuya_fila_tiene_rol(): void
    {
        $this->prepararFuentes();
        $this->prepararLiquidaciones();
        $this->asignarRol(2, RolSistema::Administrador);
        $correo = 'administracion@ejemplo.test';

        $this->actingAs(UserFactory::new()->make(['id' => 2, 'empresa' => '001', 'email' => $correo]));
        $this->get(route('reportes.comisiones'))->assertOk();

        $this->actingAs(UserFactory::new()->make(['id' => 3, 'empresa' => '002', 'email' => $correo]));
        $this->get(route('reportes.comisiones'))->assertForbidden();
        $this->get(route('reportes'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('empresa', '002')->where('roles', [])->where('rutasReportes', ['reportes.fuentes']));
    }

    public function test_la_tabla_de_roles_usa_el_prefijo_configurado(): void
    {
        config(['reportes.prefijo_tablas' => 'PRUEBA_']);

        $this->assertSame('prueba_usuario_rol', (new UsuarioRol)->getTable());
    }
}
