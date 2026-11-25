<?php

namespace Tests\Feature\Administracion;

use App\Models\EventoSeguridad;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class HistorialSeguridadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_muestra_eventos_descendentes_con_hora_de_lima_y_cambios_legibles(): void
    {
        $autor = UserFactory::new()->make(['id' => 1, 'name' => 'Autora', 'empresa' => '001']);
        $this->asignarRol(1, 'ADMINISTRADOR');
        config(['reportes.zona_horaria' => 'America/Lima', 'app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:10:44', 'UTC'));
        EventoSeguridad::registrar('ROL_CREADO', $autor, [
            'antes' => null, 'despues' => ['nombre' => 'Consulta', 'descripcion' => 'Rol del reporte.'],
        ], 'CONSULTA');
        $this->travelTo(CarbonImmutable::parse('2026-11-24 15:11:04', 'UTC'));
        EventoSeguridad::registrar('PERMISOS_ROL', $autor, [
            'nombre' => 'Consulta', 'agregados' => ['comisiones.ver'], 'quitados' => ['comisiones.obsoleto'],
        ], 'CONSULTA');
        EventoSeguridad::registrar('ROLES_USUARIO', $autor, [
            'persona' => ['nombre' => 'Ana Pérez', 'ids' => [155, 156]],
            'roles_incompletos' => false,
            'roles_anteriores' => [155 => ['GERENCIA'], 156 => ['GERENCIA']],
            'roles_nuevos' => ['CONSULTA', 'GERENCIA'],
        ], usuarios: [155, 156]);

        $this->actingAs($autor)->get(route('administracion.historial'))->assertOk()
            ->assertInertia(fn (Assert $p): Assert => $p->component('administracion-historial')
                ->has('eventos', 3)
                ->where('tipos.ROLES_USUARIO', 'Roles de usuario')
                ->where('eventos.0', [
                    'id' => 3, 'fecha' => '2026-11-24T10:11:04-05:00', 'autor' => 'Autora', 'empresa' => '001',
                    'tipo' => 'ROLES_USUARIO', 'accion' => 'Roles de Ana Pérez', 'referencia' => null,
                    'detalle' => [
                        ['etiqueta' => 'Antes', 'valor' => 'GERENCIA'],
                        ['etiqueta' => 'Después', 'valor' => 'CONSULTA, GERENCIA'],
                        ['etiqueta' => 'Agregados', 'valor' => 'CONSULTA'],
                    ],
                ])
                ->where('eventos.1.id', 2)
                ->where('eventos.1.accion', 'Permisos de CONSULTA')
                ->where('eventos.1.detalle', [
                    ['etiqueta' => 'Agregados', 'valor' => 'Ver el reporte de comisiones'],
                    ['etiqueta' => 'Quitados', 'valor' => 'comisiones.obsoleto'],
                ])
                ->where('eventos.2.id', 1)
                ->where('eventos.2.fecha', '2026-11-24T10:10:44-05:00')
                ->where('eventos.2.accion', 'Rol creado')
                ->where('eventos.2.referencia', 'CONSULTA')
                ->where('eventos.2.detalle', [
                    ['etiqueta' => 'Nombre', 'valor' => 'Consulta'],
                    ['etiqueta' => 'Descripción', 'valor' => 'Rol del reporte.'],
                ]));
    }

    public function test_eventos_anteriores_sin_nombre_muestran_el_correo_y_los_roles_por_cuenta(): void
    {
        $autor = UserFactory::new()->make(['id' => 1, 'name' => 'Autora', 'empresa' => '001']);
        $this->asignarRol(1, 'ADMINISTRADOR');
        EventoSeguridad::registrar('ROLES_USUARIO', $autor, [
            'persona' => ['ids' => [155, 156], 'correos' => ['ana@ejemplo.test']],
            'roles_incompletos' => true,
            'roles_anteriores' => [155 => ['GERENCIA'], 156 => []],
            'roles_nuevos' => ['GERENCIA'],
        ], 'GERENCIA', [155, 156]);

        $this->actingAs($autor)->get(route('administracion.historial'))->assertOk()
            ->assertInertia(fn (Assert $p): Assert => $p
                ->where('eventos.0.accion', 'Roles de ana@ejemplo.test')
                ->where('eventos.0.detalle', [
                    ['etiqueta' => 'Antes en cuenta 155', 'valor' => 'GERENCIA'],
                    ['etiqueta' => 'Antes en cuenta 156', 'valor' => 'Sin rol'],
                    ['etiqueta' => 'Después', 'valor' => 'GERENCIA'],
                ]));
    }

    public function test_historial_solo_entrega_los_200_eventos_mas_recientes(): void
    {
        $this->asignarRol(1, 'ADMINISTRADOR');
        DB::table('seguridad_evento')->insert(array_fill(0, 201, [
            'accion' => 'ROL_CREADO', 'rol' => 'CONSULTA', 'autor_id' => 1,
            'autor_nombre' => 'Autora', 'fecha' => '2026-11-24 10:00:00',
        ]));

        $this->actingAs(UserFactory::new()->make(['id' => 1]))->get(route('administracion.historial'))->assertOk()
            ->assertInertia(fn (Assert $p): Assert => $p
                ->has('eventos', 200)->where('eventos.0.id', 201)->where('eventos.199.id', 2));
    }

    public function test_administrador_ve_la_entrada_y_abre_historial(): void
    {
        $this->asignarRol(1, 'ADMINISTRADOR');

        $this->actingAs(UserFactory::new()->make(['id' => 1]))->get(route('administracion'))->assertOk()
            ->assertInertia(fn (Assert $p): Assert => $p->where('rutasAdministracion', [
                'administracion.laboratorios', 'administracion.usuarios', 'administracion.roles', 'administracion.historial',
            ]));
        $this->get(route('administracion.historial'))->assertOk();
    }

    #[TestWith(['administracion.usuarios'])]
    #[TestWith(['administracion.roles'])]
    public function test_un_permiso_exclusivo_guardado_en_otro_rol_no_abre_historial(string $permiso): void
    {
        $this->crearRol('GESTOR', [$permiso]);
        $this->asignarRol(1, 'GESTOR');

        $this->actingAs(UserFactory::new()->make(['id' => 1]))->get(route('administracion'))->assertForbidden();
        $this->get(route('administracion.historial'))->assertForbidden();
    }

    public function test_laboratorios_sin_roles_ni_usuarios_recibe_403_en_historial(): void
    {
        $this->crearRol('LABORATORIOS', ['administracion.laboratorios']);
        $this->asignarRol(1, 'LABORATORIOS');

        $this->actingAs(UserFactory::new()->make(['id' => 1]))->get(route('administracion'))->assertOk()
            ->assertInertia(fn (Assert $p): Assert => $p->where('rutasAdministracion', ['administracion.laboratorios']));
        $this->get(route('administracion.historial'))->assertForbidden();
    }

    public function test_historial_redirige_al_login_sin_sesion(): void
    {
        $this->get(route('administracion.historial'))->assertRedirectToRoute('login');
    }
}
