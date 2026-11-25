<?php

namespace Tests\Feature\Administracion;

use App\Models\EventoSeguridad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararUsuarios();
    }

    public function test_lista_roles_y_agrupa_las_cuentas_por_dni_para_contar_personas(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->crearPersonaUsuario('Gestora', '12345678', [
            '001' => 'gestora@empresa-uno.test',
            '002' => 'gestora@empresa-dos.test',
        ]);
        foreach ($cuentas as $cuenta) {
            $this->asignarRol($cuenta->id, 'GERENCIA');
        }

        $this->actingAs($autor)->get(route('administracion.roles'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->component('administracion-roles')
                ->where('roles', ['ADMINISTRADOR'])
                ->has('rolesRegistrados', 2)
                ->where('rolesRegistrados.0', [
                    'codigo' => 'ADMINISTRADOR', 'nombre' => 'Administrador', 'descripcion' => null, 'es_sistema' => true,
                    'personas' => 1, 'permisos' => 6, 'motivo_no_eliminar' => 'No puedes eliminar un rol de sistema.',
                ])
                ->where('rolesRegistrados.1.personas', 1)
                ->where('rolesRegistrados.1.permisos', 3)
                ->where('rolesRegistrados.1.motivo_no_eliminar', 'Quita el rol de las personas asignadas antes de eliminarlo.'));
        $this->get(route('administracion.roles.mostrar', 'GERENCIA'))->assertOk()
            ->assertInertia(fn (Assert $pagina): Assert => $pagina
                ->component('administracion-rol')
                ->where('rol.permisos', ['comisiones.ver', 'comisiones.aprobar', 'comisiones.rechazar'])
                ->where('personas', [[
                    'nombre' => 'Gestora',
                    'correos' => ['gestora@empresa-uno.test', 'gestora@empresa-dos.test'],
                ]]));
    }

    public function test_crea_codigos_sin_tildes_y_agrega_sufijos_si_ya_existen(): void
    {
        $autor = $this->administrador();
        $this->crearRol('AREA_DE_REVISION', []);
        $this->crearRol('AREA_DE_REVISION_2', []);

        $this->actingAs($autor)->post(route('administracion.roles.crear'), [
            'nombre' => 'Área de revisión',
            'descripcion' => 'Revisa las liquidaciones.',
        ])->assertRedirectToRoute('administracion.roles.mostrar', 'AREA_DE_REVISION_3')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rol', [
            'codigo' => 'AREA_DE_REVISION_3', 'nombre' => 'Área de revisión', 'descripcion' => 'Revisa las liquidaciones.', 'es_sistema' => 'N',
        ]);
        $this->assertDatabaseCount('rol_permiso', 9);
        $evento = EventoSeguridad::query()->sole();
        $this->assertSame('ROL_CREADO', $evento->accion);
        $this->assertSame('AREA_DE_REVISION_3', $evento->rol);
        $this->assertSame($autor->id, (int) $evento->autor_id);
        $this->assertSame('001', $evento->autor_empresa);
        $this->assertSame([
            'antes' => null,
            'despues' => ['nombre' => 'Área de revisión', 'descripcion' => 'Revisa las liquidaciones.'],
        ], $this->detalle($evento));
    }

    public function test_el_codigo_con_sufijo_no_supera_30_caracteres(): void
    {
        $this->crearRol(str_repeat('A', 30), []);
        $codigo = str_repeat('A', 28).'_2';

        $this->actingAs($this->administrador())->post(route('administracion.roles.crear'), [
            'nombre' => str_repeat('Á', 60),
        ])->assertRedirectToRoute('administracion.roles.mostrar', $codigo)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rol', ['codigo' => $codigo, 'descripcion' => null]);
    }

    #[TestWith(['', 'Escribe el nombre del rol.'])]
    #[TestWith(['AB', 'El nombre debe tener al menos 3 caracteres.'])]
    #[TestWith(['✨✨✨', 'Escribe un nombre con letras o números que permitan generar el código del rol.'])]
    public function test_rechaza_nombres_invalidos_sin_crear_rol_ni_evento(string $nombre, string $mensaje): void
    {
        $this->actingAs($this->administrador())->from(route('administracion.roles'))
            ->post(route('administracion.roles.crear'), compact('nombre'))
            ->assertRedirectToRoute('administracion.roles')->assertSessionHasErrors(['nombre' => $mensaje]);

        $this->assertDatabaseCount('rol', 2);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_valida_las_longitudes_maximas_del_nombre_y_de_la_descripcion(): void
    {
        $this->actingAs($this->administrador())->post(route('administracion.roles.crear'), [
            'nombre' => str_repeat('a', 61),
            'descripcion' => str_repeat('a', 301),
        ])->assertSessionHasErrors([
            'nombre' => 'El nombre no puede superar los 60 caracteres.',
            'descripcion' => 'La descripción no puede superar los 300 caracteres.',
        ]);

        $this->assertDatabaseCount('rol', 2);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_detecta_nombres_repetidos_sin_distinguir_mayusculas_incluso_con_tildes(): void
    {
        $this->crearRol('EXITO', []);
        Rol::query()->whereKey('EXITO')->update(['nombre' => 'Éxito']);
        $this->actingAs($this->administrador());

        $this->post(route('administracion.roles.crear'), ['nombre' => 'éXITO'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un rol con ese nombre.']);
        $this->put(route('administracion.roles.actualizar', 'GERENCIA'), ['nombre' => 'éxito'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un rol con ese nombre.']);

        $this->assertDatabaseCount('rol', 3);
        $this->assertDatabaseHas('rol', ['codigo' => 'GERENCIA', 'nombre' => 'Gerencia']);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_editar_solo_cambia_el_nombre_y_la_descripcion(): void
    {
        $this->actingAs($this->administrador())->put(route('administracion.roles.actualizar', 'GERENCIA'), [
            'nombre' => 'Gerencia regional',
            'descripcion' => 'Revisión regional.',
            'codigo' => 'OTRO_CODIGO',
            'es_sistema' => 'S',
        ])->assertRedirectToRoute('administracion.roles')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rol', [
            'codigo' => 'GERENCIA', 'nombre' => 'Gerencia regional', 'descripcion' => 'Revisión regional.', 'es_sistema' => 'N',
        ]);
        $this->assertDatabaseMissing('rol', ['codigo' => 'OTRO_CODIGO']);
        $this->assertSame(['comisiones.aprobar', 'comisiones.rechazar', 'comisiones.ver'], $this->permisos('GERENCIA'));
        $evento = EventoSeguridad::query()->sole();
        $this->assertSame('ROL_EDITADO', $evento->accion);
        $this->assertSame('GERENCIA', $evento->rol);
        $this->assertSame([
            'antes' => ['nombre' => 'Gerencia', 'descripcion' => null],
            'despues' => ['nombre' => 'Gerencia regional', 'descripcion' => 'Revisión regional.'],
        ], $this->detalle($evento));
    }

    public function test_guardar_permisos_actualiza_el_catalogo_vigente_y_conserva_los_obsoletos(): void
    {
        $this->crearRol('REVISION', ['comisiones.ver', 'comisiones.rechazar', 'comisiones.obsoleto']);
        $this->actingAs($this->administrador())->put(route('administracion.roles.permisos', 'REVISION'), [
            'permisos' => ['comisiones.ver', 'comisiones.aprobar'],
        ])->assertRedirectToRoute('administracion.roles.mostrar', 'REVISION')->assertSessionHasNoErrors();

        $this->assertSame(['comisiones.aprobar', 'comisiones.obsoleto', 'comisiones.ver'], $this->permisos('REVISION'));
        $evento = EventoSeguridad::query()->sole();
        $this->assertSame('PERMISOS_ROL', $evento->accion);
        $this->assertSame('REVISION', $evento->rol);
        $this->assertSame([
            'nombre' => 'REVISION',
            'antes' => ['comisiones.obsoleto', 'comisiones.rechazar', 'comisiones.ver'],
            'despues' => ['comisiones.aprobar', 'comisiones.obsoleto', 'comisiones.ver'],
            'agregados' => ['comisiones.aprobar'],
            'quitados' => ['comisiones.rechazar'],
        ], $this->detalle($evento));
        $this->get(route('administracion.roles.mostrar', 'REVISION'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('rol.permisos', ['comisiones.ver', 'comisiones.aprobar'])
            ->where('rol.obsoletos', ['comisiones.obsoleto']));
    }

    public function test_administrador_conserva_los_tres_permisos_fijos_aunque_se_omitan(): void
    {
        $this->actingAs($this->administrador())->put(route('administracion.roles.permisos', 'ADMINISTRADOR'), [
            'permisos' => ['comisiones.ver'],
        ])->assertRedirectToRoute('administracion.roles.mostrar', 'ADMINISTRADOR')->assertSessionHasNoErrors();

        $this->assertSame([
            'administracion.laboratorios', 'administracion.roles', 'administracion.usuarios', 'comisiones.ver',
        ], $this->permisos('ADMINISTRADOR'));
        $detalle = $this->detalle(EventoSeguridad::query()->sole());
        $this->assertSame(['comisiones.enviar', 'comisiones.procesar'], $detalle['quitados']);
        $this->get(route('administracion.roles.mostrar', 'ADMINISTRADOR'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('catalogo.0.modulo', 'Administración')
            ->where('catalogo.0.permisos.0.fijo', true)
            ->where('catalogo.0.permisos.1.fijo', true)
            ->where('catalogo.0.permisos.2.fijo', true)
            ->where('catalogo.1.permisos.0.fijo', false));
    }

    public function test_una_lista_vacia_quita_los_permisos_editables(): void
    {
        $this->actingAs($this->administrador())->put(route('administracion.roles.permisos', 'GERENCIA'), ['permisos' => []])
            ->assertRedirectToRoute('administracion.roles.mostrar', 'GERENCIA')->assertSessionHasNoErrors();

        $this->assertSame([], $this->permisos('GERENCIA'));
        $this->assertSame(['comisiones.aprobar', 'comisiones.rechazar', 'comisiones.ver'],
            $this->detalle(EventoSeguridad::query()->sole())['quitados']);
    }

    public function test_guardar_sin_cambios_no_genera_eventos(): void
    {
        $this->actingAs($this->administrador());

        $this->put(route('administracion.roles.actualizar', 'GERENCIA'), ['nombre' => 'Gerencia', 'descripcion' => null])
            ->assertRedirectToRoute('administracion.roles')->assertSessionHasNoErrors();
        $this->put(route('administracion.roles.permisos', 'GERENCIA'), [
            'permisos' => ['comisiones.ver', 'comisiones.rechazar', 'comisiones.aprobar'],
        ])->assertRedirectToRoute('administracion.roles.mostrar', 'GERENCIA')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    #[TestWith([['permisos' => ['comisiones.inexistente']], 'permisos.0', 'Elige un permiso del catálogo.'])]
    #[TestWith([['permisos' => ['codigo' => 'comisiones.ver']], 'permisos', 'Envía los permisos como una lista.'])]
    #[TestWith([[], 'permisos', 'Envía los permisos del rol.'])]
    public function test_rechaza_permisos_fuera_del_catalogo_o_formato_invalido(array $datos, string $campo, string $mensaje): void
    {
        $this->actingAs($this->administrador())->put(route('administracion.roles.permisos', 'GERENCIA'), $datos)
            ->assertSessionHasErrors([$campo => $mensaje]);

        $this->assertSame(['comisiones.aprobar', 'comisiones.rechazar', 'comisiones.ver'], $this->permisos('GERENCIA'));
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    #[TestWith(['ADMINISTRADOR', 'No puedes eliminar un rol de sistema.'])]
    #[TestWith(['GERENCIA', 'Quita el rol de las personas asignadas antes de eliminarlo.'])]
    public function test_protege_roles_de_sistema_y_roles_con_personas(string $codigo, string $mensaje): void
    {
        $gerencia = $this->crearPersonaUsuario('Gestora', '12345678', ['001' => 'gestora@ejemplo.test'])->first();
        $this->asignarRol($gerencia->id, 'GERENCIA');

        $this->actingAs($this->administrador())->delete(route('administracion.roles.eliminar', $codigo))
            ->assertSessionHasErrors(['rol' => $mensaje]);

        $this->assertDatabaseCount('rol', 2);
        $this->assertDatabaseCount('rol_permiso', 9);
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    public function test_eliminar_un_rol_sin_personas_borra_los_permisos_y_conserva_el_evento(): void
    {
        $this->crearRol('CONSULTA', ['comisiones.ver']);

        $this->actingAs($this->administrador())->delete(route('administracion.roles.eliminar', 'CONSULTA'))
            ->assertRedirectToRoute('administracion.roles')->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('rol', ['codigo' => 'CONSULTA']);
        $this->assertDatabaseMissing('rol_permiso', ['rol' => 'CONSULTA']);
        $evento = EventoSeguridad::query()->sole();
        $this->assertSame('ROL_ELIMINADO', $evento->accion);
        $this->assertSame('CONSULTA', $evento->rol);
        $this->assertSame([
            'antes' => ['nombre' => 'CONSULTA', 'descripcion' => null, 'permisos' => ['comisiones.ver']],
            'despues' => null,
        ], $this->detalle($evento));
    }

    #[TestWith(['administracion.usuarios'])]
    #[TestWith(['administracion.roles'])]
    public function test_otro_rol_no_puede_recibir_los_permisos_exclusivos_de_administrador(string $permiso): void
    {
        $this->actingAs($this->administrador())->put(route('administracion.roles.permisos', 'GERENCIA'), [
            'permisos' => ['comisiones.ver', $permiso],
        ])->assertSessionHasErrors(['permisos' => 'Solo el rol Administrador puede gestionar usuarios y roles.']);

        $this->assertSame(['comisiones.aprobar', 'comisiones.rechazar', 'comisiones.ver'], $this->permisos('GERENCIA'));
        $this->assertDatabaseCount('seguridad_evento', 0);
        $this->get(route('administracion.roles.mostrar', 'GERENCIA'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('catalogo.0.permisos.0.exclusivo', false)
            ->where('catalogo.0.permisos.1.exclusivo', true)
            ->where('catalogo.0.permisos.2.exclusivo', true));
        $this->get(route('administracion.roles.mostrar', 'ADMINISTRADOR'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('catalogo.0.permisos.1.exclusivo', false)
            ->where('catalogo.0.permisos.2.exclusivo', false));
    }

    public function test_los_permisos_exclusivos_guardados_en_otro_rol_no_dan_acceso_y_se_limpian_al_guardar(): void
    {
        $this->crearRol('GESTION_ROLES', ['administracion.roles', 'administracion.usuarios', 'comisiones.ver']);
        $usuario = $this->crearPersonaUsuario('Gestora', '12345678', ['001' => 'gestora@ejemplo.test'])->first();
        $this->asignarRol($usuario->id, 'GESTION_ROLES');

        $this->actingAs($usuario);
        $this->assertSame(['comisiones.ver'], $usuario->codigosPermiso());
        $this->get(route('administracion'))->assertForbidden();
        $this->get(route('administracion.roles'))->assertForbidden();
        $this->get(route('administracion.usuarios'))->assertForbidden();
        $this->get(route('administracion.historial'))->assertForbidden();

        $this->actingAs($this->administrador());
        $this->get(route('administracion.roles.mostrar', 'GESTION_ROLES'))->assertInertia(fn (Assert $pagina): Assert => $pagina
            ->where('rol.permisos', ['comisiones.ver']));
        $this->put(route('administracion.roles.permisos', 'GESTION_ROLES'), ['permisos' => ['comisiones.ver']])
            ->assertRedirectToRoute('administracion.roles.mostrar', 'GESTION_ROLES')->assertSessionHasNoErrors();

        $this->assertSame(['comisiones.ver'], $this->permisos('GESTION_ROLES'));
        $this->assertSame(['administracion.roles', 'administracion.usuarios'],
            $this->detalle(EventoSeguridad::query()->sole())['quitados']);
    }

    #[TestWith(['GET', 'mostrar'])]
    #[TestWith(['PUT', 'actualizar'])]
    #[TestWith(['PUT', 'permisos'])]
    #[TestWith(['DELETE', 'eliminar'])]
    public function test_un_rol_inexistente_respeta_la_autorizacion_antes_del_404(string $metodo, string $accion): void
    {
        $autor = $this->administrador();
        $gerencia = $this->crearPersonaUsuario('Gestora', '12345678', ['001' => 'gestora@ejemplo.test'])->first();
        $this->asignarRol($gerencia->id, 'GERENCIA');
        $url = route("administracion.roles.$accion", 'NO_EXISTE');

        $this->actingAs($gerencia)->call($metodo, $url)->assertForbidden();
        $this->actingAs($autor)->call($metodo, $url)->assertNotFound();
        $this->assertDatabaseCount('seguridad_evento', 0);
    }

    private function administrador(): User
    {
        $autor = $this->crearPersonaUsuario('Autor', '11111111', ['001' => 'autor@ejemplo.test'])->first();
        $this->asignarRol($autor->id, 'ADMINISTRADOR');

        return $autor;
    }

    /** @return list<string> */
    private function permisos(string $codigo): array
    {
        return DB::table('rol_permiso')->where('rol', $codigo)->orderBy('permiso')->pluck('permiso')->all();
    }

    /** @return array<string, mixed> */
    private function detalle(EventoSeguridad $evento): array
    {
        return json_decode($evento->detalle, true, flags: JSON_THROW_ON_ERROR);
    }
}
