<?php

namespace Tests\Feature\Reportes;

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

    #[TestWith(['ADMINISTRADOR'])]
    #[TestWith(['GERENCIA'])]
    public function test_ambos_roles_pueden_abrir_el_reporte_y_reciben_su_acceso(string $rol): void
    {
        $this->prepararComisiones();
        $this->prepararFuentes();
        $this->prepararLiquidaciones();
        $this->asignarRol(1, $rol);
        $this->actingAs(UserFactory::new()->make(['id' => 1]));

        $this->get(route('reportes'))->assertOk()->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('roles', [$rol])->where('rutasReportes', ['reportes.comisiones', 'reportes.fuentes']));
        $this->get(route('reportes.comisiones'))->assertOk();
        $this->get(route('reportes.comisiones.liquidaciones.mostrar', '2026-10'))->assertOk();
    }

    /** @param list<string> $permitidas */
    #[TestWith([null, []])]
    #[TestWith(['ADMINISTRADOR', ['ver', 'procesar', 'enviar']])]
    #[TestWith(['GERENCIA', ['ver', 'aprobar', 'rechazar']])]
    public function test_cada_rol_solo_tiene_los_permisos_de_su_funcion(?string $rol, array $permitidas): void
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
        $this->asignarRol(2, 'ADMINISTRADOR');
        $correo = 'administracion@ejemplo.test';

        $this->actingAs(UserFactory::new()->make(['id' => 2, 'empresa' => '001', 'email' => $correo]));
        $this->get(route('reportes.comisiones'))->assertOk();

        $this->actingAs(UserFactory::new()->make(['id' => 3, 'empresa' => '002', 'email' => $correo]));
        $this->get(route('reportes.comisiones'))->assertForbidden();
        $this->get(route('reportes'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('empresa', '002')->where('roles', [])->where('rutasReportes', ['reportes.fuentes']));
    }

    public function test_un_rol_nuevo_de_consulta_abre_comisiones_pero_recibe_403_al_procesar(): void
    {
        $this->prepararFuentes();
        $this->prepararLiquidaciones();
        $this->crearRol('CONSULTA', ['comisiones.ver']);
        $this->asignarRol(1, 'CONSULTA');
        $usuario = UserFactory::new()->make(['id' => 1]);

        $this->actingAs($usuario)->get(route('reportes.comisiones'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->where('permisosEfectivos', ['comisiones.ver'])->missing('puede_elegir_periodo'));
        $this->post(route('reportes.comisiones.liquidaciones.procesar', '2026-10'))->assertForbidden();
        $this->assertDatabaseCount('com_liq', 0);
    }

    public function test_suma_permisos_de_dos_roles_sin_repetir_consultas_ni_codigos(): void
    {
        $this->crearRol('CONSULTA', ['comisiones.ver', 'comisiones.procesar']);
        $this->asignarRol(1, 'CONSULTA');
        $this->asignarRol(1, 'GERENCIA');
        $usuario = UserFactory::new()->make(['id' => 1]);

        $this->expectsDatabaseQueryCount(3, 'sqlite');
        $this->assertTrue($usuario->can('comisiones.procesar'));
        $this->assertTrue($usuario->can('comisiones.aprobar'));
        $this->assertFalse($usuario->can('comisiones.enviar'));
        $this->assertSame(['comisiones.ver', 'comisiones.procesar', 'comisiones.aprobar', 'comisiones.rechazar'], $usuario->codigosPermiso());
    }

    public function test_ignora_los_permisos_obsoletos_guardados_en_un_rol(): void
    {
        $this->crearRol('CONSULTA', ['comisiones.ver', 'administracion.obsoleto']);
        $this->asignarRol(1, 'CONSULTA');
        $usuario = UserFactory::new()->make(['id' => 1]);

        $this->assertSame(['comisiones.ver'], $usuario->codigosPermiso());
        $this->assertFalse($usuario->tienePermiso('administracion.obsoleto'));
        $this->assertFalse($usuario->can('administracion.ver'));
    }

    public function test_la_tabla_de_roles_usa_el_prefijo_configurado(): void
    {
        config(['reportes.prefijo_tablas' => 'PRUEBA_']);

        $this->assertSame('prueba_usuario_rol', (new UsuarioRol)->getTable());
    }
}
