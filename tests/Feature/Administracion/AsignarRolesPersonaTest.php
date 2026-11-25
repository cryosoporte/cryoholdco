<?php

namespace Tests\Feature\Administracion;

use App\Administracion\AsignarRolesPersona;
use App\Models\EventoSeguridad;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AsignarRolesPersonaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuarios();
    }

    public function test_asigna_gerencia_a_las_dos_cuentas_y_registra_el_evento_con_el_contexto_del_cambio(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona();

        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), ['roles' => ['GERENCIA']])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, ['GERENCIA']);
        foreach ($cuentas as $cuenta) {
            $this->assertTrue($cuenta->fresh()->can('comisiones.ver'));
        }

        $evento = EventoSeguridad::query()->sole();
        $this->assertDatabaseHas('seguridad_evento', [
            'id' => $evento->id,
            'accion' => 'ROLES_USUARIO',
            'rol' => 'GERENCIA',
            'usuarios' => $cuentas['001']->id.','.$cuentas['002']->id,
            'autor_id' => $autor->id,
            'autor_nombre' => 'Autor',
            'autor_empresa' => '001',
        ]);
        $this->assertSame([
            'autor_correo' => 'autor@ejemplo.test',
            'persona' => [
                'ids' => [$cuentas['001']->id, $cuentas['002']->id],
                'correos' => ['persona@empresa-uno.test', 'persona@empresa-dos.test'],
                'nombre' => 'Persona',
            ],
            'roles_incompletos' => false,
            'roles_anteriores' => [$cuentas['001']->id => [], $cuentas['002']->id => []],
            'roles_nuevos' => ['GERENCIA'],
        ], json_decode($evento->detalle, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_asigna_dos_roles_a_las_dos_cuentas_y_cada_cuenta_combina_sus_permisos(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona();
        $this->crearRol('PROCESAR', ['comisiones.procesar']);

        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), [
            'roles' => ['PROCESAR', 'GERENCIA'],
        ])->assertRedirectToRoute('administracion.usuarios')->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, ['GERENCIA', 'PROCESAR']);
        foreach ($cuentas as $cuenta) {
            $conRoles = $cuenta->fresh();
            $this->assertTrue($conRoles->can('comisiones.aprobar'));
            $this->assertTrue($conRoles->can('comisiones.procesar'));
        }
        $evento = EventoSeguridad::query()->sole();
        $this->assertNull($evento->rol);
        $this->assertSame(['GERENCIA', 'PROCESAR'], json_decode($evento->detalle, true, flags: JSON_THROW_ON_ERROR)['roles_nuevos']);
    }

    public function test_quitar_un_rol_conserva_las_asignaciones_y_fechas_del_otro(): void
    {
        $autor = $this->administrador();
        $this->crearRol('CONSULTA', ['comisiones.ver']);
        $cuentas = $this->persona(['CONSULTA', 'GERENCIA']);
        $fechaAnterior = '2026-10-19 14:30:00';
        DB::table('usuario_rol')->whereIn('user_id', $cuentas->pluck('id'))
            ->where('rol', 'GERENCIA')->update(['fecha_asignacion' => $fechaAnterior]);

        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), [
            'roles' => ['GERENCIA'],
        ])->assertRedirectToRoute('administracion.usuarios')->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, ['GERENCIA']);
        foreach ($cuentas as $cuenta) {
            $this->assertDatabaseHas('usuario_rol', [
                'user_id' => $cuenta->id, 'rol' => 'GERENCIA', 'fecha_asignacion' => $fechaAnterior,
            ]);
        }
        $this->assertDatabaseHas('seguridad_evento', ['accion' => 'ROLES_USUARIO', 'rol' => 'GERENCIA']);
    }

    public function test_la_asignacion_y_el_evento_se_revierten_juntos(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona();

        DB::beginTransaction();
        try {
            app(AsignarRolesPersona::class)->asignar($autor, $cuentas['001'], ['GERENCIA']);

            $this->comprobarRoles($cuentas, ['GERENCIA']);
            $this->assertDatabaseCount('seguridad_evento', 1);
        } finally {
            DB::rollBack();
        }

        $this->comprobarRoles($cuentas, []);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_acepta_un_rol_nuevo_del_catalogo_de_la_base(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(['GERENCIA']);
        $this->crearRol('CONSULTA', ['comisiones.ver']);

        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), ['roles' => ['CONSULTA']])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, ['CONSULTA']);
        $this->assertDatabaseHas('seguridad_evento', ['accion' => 'ROLES_USUARIO', 'rol' => 'CONSULTA']);
    }

    public function test_cambia_de_gerencia_a_administrador_en_todas_las_cuentas(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(['GERENCIA']);

        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), ['roles' => ['ADMINISTRADOR']])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, ['ADMINISTRADOR']);
    }

    public function test_una_lista_vacia_quita_todos_los_roles_y_conserva_las_asignaciones_ajenas(): void
    {
        $autor = $this->administrador();
        $this->crearRol('CONSULTA', ['comisiones.ver']);
        $cuentas = $this->persona(['CONSULTA', 'GERENCIA']);
        $otraPersona = $this->crearPersonaUsuario('Tercera persona', '33333333', ['002' => 'tercera@ejemplo.test']);
        $this->asignarRol($otraPersona['002']->id, 'GERENCIA');

        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), ['roles' => []])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, []);
        $this->comprobarRoles($otraPersona, ['GERENCIA']);
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $autor->id, 'rol' => 'ADMINISTRADOR']);
    }

    public function test_unifica_los_roles_incompletos_al_guardar_el_conjunto_del_listado(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona();
        $this->asignarRol($cuentas['001']->id, 'ADMINISTRADOR');
        $this->asignarRol($cuentas['001']->id, 'GERENCIA');
        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $cuentas['001']->id), [
            'roles' => ['ADMINISTRADOR', 'GERENCIA'],
        ])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRoles($cuentas, ['ADMINISTRADOR', 'GERENCIA']);
        $evento = EventoSeguridad::query()->sole();
        $this->assertSame('ROLES_USUARIO', $evento->accion);
        $detalle = json_decode($evento->detalle, true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($detalle['roles_incompletos']);
        $this->assertSame([$cuentas['001']->id => ['ADMINISTRADOR', 'GERENCIA'], $cuentas['002']->id => []], $detalle['roles_anteriores']);
        $this->assertSame(['ADMINISTRADOR', 'GERENCIA'], $detalle['roles_nuevos']);
    }

    public function test_guardar_los_mismos_roles_conserva_las_fechas_y_no_registra_un_cambio(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(['ADMINISTRADOR', 'GERENCIA']);
        $fechaAnterior = '2026-10-19 14:30:00';
        DB::table('usuario_rol')->whereIn('user_id', $cuentas->pluck('id'))
            ->update(['fecha_asignacion' => $fechaAnterior]);

        $resultado = app(AsignarRolesPersona::class)->asignar($autor, $cuentas['001'], ['GERENCIA', 'ADMINISTRADOR']);

        $this->assertSame(['cambio' => false], $resultado);
        $this->comprobarRoles($cuentas, ['ADMINISTRADOR', 'GERENCIA']);
        $this->assertSame(
            [$fechaAnterior],
            DB::table('usuario_rol')->whereIn('user_id', $cuentas->pluck('id'))->distinct()->pluck('fecha_asignacion')->all(),
        );
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    /** @param list<string> $roles */
    #[TestWith([[]])]
    #[TestWith([['GERENCIA']])]
    public function test_rechaza_cambiar_los_roles_propios_desde_el_id_de_la_otra_empresa(array $roles): void
    {
        $cuentas = $this->persona(['ADMINISTRADOR']);
        $this->actingAs($cuentas['001'])->from(route('administracion.usuarios'))
            ->put(route('administracion.usuarios.roles', $cuentas['002']->id), compact('roles'))
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasErrors(['roles' => 'No puedes cambiar tus propios roles.']);

        $this->comprobarRoles($cuentas, ['ADMINISTRADOR']);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_revierte_el_cambio_si_quitaria_al_ultimo_administrador(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(['ADMINISTRADOR']);
        DB::table('usuario_rol')->where('user_id', $autor->id)->delete();
        $this->asignarRol(999, 'ADMINISTRADOR');
        try {
            app(AsignarRolesPersona::class)->asignar($autor, $cuentas['001'], []);
            $this->fail('La operación debía rechazar el retiro del último administrador.');
        } catch (ValidationException $error) {
            $this->assertSame(['roles' => ['Debe quedar al menos una persona con rol Administrador.']], $error->errors());
        }

        $this->comprobarRoles($cuentas, ['ADMINISTRADOR']);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    /** @param array{roles?: mixed} $datos */
    #[TestWith([['roles' => ['GERENCIA', 'OTRO']], 'roles.1', 'Elige un rol válido.'])]
    #[TestWith([['roles' => [['GERENCIA']]], 'roles.0', 'Elige un rol válido.'])]
    #[TestWith([['roles' => 'GERENCIA'], 'roles', 'Envía los roles como una lista.'])]
    #[TestWith([['roles' => ['rol' => 'GERENCIA']], 'roles', 'Envía los roles como una lista.'])]
    #[TestWith([[], 'roles', 'Envía los roles de la persona.'])]
    public function test_una_lista_invalida_o_ausente_conserva_las_asignaciones(array $datos, string $campo, string $mensaje): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(['GERENCIA']);
        $this->actingAs($autor)->from(route('administracion.usuarios'))
            ->put(route('administracion.usuarios.roles', $cuentas['001']->id), $datos)
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->comprobarRoles($cuentas, ['GERENCIA']);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_el_id_inexistente_da_404_y_no_registra_un_cambio(): void
    {
        $autor = $this->administrador();
        $this->actingAs($autor)->put(route('administracion.usuarios.roles', $autor->id + 1), ['roles' => ['GERENCIA']])
            ->assertNotFound();

        $this->assertDatabaseCount('usuario_rol', 1);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    private function administrador(): User
    {
        $autor = $this->crearPersonaUsuario('Autor', '11111111', ['001' => 'autor@ejemplo.test'])['001'];
        $this->asignarRol($autor->id, 'ADMINISTRADOR');

        return $autor;
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<string, User>
     */
    private function persona(array $roles = []): Collection
    {
        $cuentas = $this->crearPersonaUsuario('Persona', '22222222', [
            '001' => 'persona@empresa-uno.test',
            '002' => 'persona@empresa-dos.test',
        ]);
        foreach ($cuentas as $cuenta) {
            foreach ($roles as $rol) {
                $this->asignarRol($cuenta->id, $rol);
            }
        }

        return $cuentas;
    }

    /**
     * @param  Collection<string, User>  $cuentas
     * @param  list<string>  $roles
     */
    private function comprobarRoles(Collection $cuentas, array $roles): void
    {
        $asignaciones = DB::table('usuario_rol')->whereIn('user_id', $cuentas->pluck('id'))
            ->orderBy('user_id')
            ->orderBy('rol')
            ->get(['user_id', 'rol'])
            ->map(fn (object $asignacion): array => (array) $asignacion)
            ->all();
        $esperadas = $cuentas->flatMap(
            fn (User $cuenta): array => array_map(
                fn (string $rol): array => ['user_id' => $cuenta->id, 'rol' => $rol],
                $roles,
            ),
        )->values()->all();

        $this->assertSame($esperadas, $asignaciones);
    }
}
