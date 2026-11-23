<?php

namespace Tests\Feature\Administracion;

use App\Administracion\AsignarRolPersona;
use App\Administracion\RolSistema;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AsignarRolPersonaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuarios();
    }

    public function test_asigna_gerencia_a_las_dos_cuentas_y_registra_el_cambio_despues_del_commit(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona();
        $contextoEsperado = [
            'autor' => ['id' => $autor->id, 'email' => 'autor@ejemplo.test', 'empresa' => '001'],
            'persona' => [
                'ids' => [$cuentas['001']->id, $cuentas['002']->id],
                'correos' => ['persona@empresa-uno.test', 'persona@empresa-dos.test'],
            ],
            'rol_anterior' => null,
            'rol_nuevo' => 'GERENCIA',
        ];
        Log::shouldReceive('info')->once()->withArgs(function (string $mensaje, array $contexto) use ($contextoEsperado): bool {
            $this->assertSame(0, DB::transactionLevel());

            return $mensaje === 'Rol de usuario actualizado' && $contexto === $contextoEsperado;
        });

        $this->actingAs($autor)->put(route('administracion.usuarios.rol', $cuentas['001']->id), ['rol' => 'GERENCIA'])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRol($cuentas, RolSistema::Gerencia);
        foreach ($cuentas as $cuenta) {
            $this->assertTrue($cuenta->fresh()->can('comisiones.ver'));
        }
    }

    public function test_cambia_de_gerencia_a_administrador_en_todas_las_cuentas(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(RolSistema::Gerencia);

        $this->actingAs($autor)->put(route('administracion.usuarios.rol', $cuentas['001']->id), ['rol' => 'ADMINISTRADOR'])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRol($cuentas, RolSistema::Administrador);
    }

    public function test_quita_el_rol_de_todas_las_cuentas_y_conserva_los_roles_ajenos(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(RolSistema::Gerencia);
        $otraPersona = $this->crearPersonaUsuario('Tercera persona', '33333333', ['002' => 'tercera@ejemplo.test']);
        $this->asignarRol($otraPersona['002']->id, RolSistema::Gerencia);

        $this->actingAs($autor)->put(route('administracion.usuarios.rol', $cuentas['001']->id), ['rol' => null])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRol($cuentas, null);
        $this->comprobarRol($otraPersona, RolSistema::Gerencia);
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $autor->id, 'rol' => 'ADMINISTRADOR']);
    }

    public function test_unifica_el_rol_incompleto_al_guardar_el_rol_que_muestra_el_listado(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona();
        $this->asignarRol($cuentas['001']->id, RolSistema::Administrador);
        $this->asignarRol($cuentas['001']->id, RolSistema::Gerencia);
        Log::spy();

        $this->actingAs($autor)->put(route('administracion.usuarios.rol', $cuentas['001']->id), ['rol' => 'ADMINISTRADOR'])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasNoErrors();

        $this->comprobarRol($cuentas, RolSistema::Administrador);
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $mensaje, array $contexto): bool => $mensaje === 'Rol de usuario actualizado'
                && $contexto['rol_anterior'] === 'incompleto'
                && $contexto['rol_nuevo'] === 'ADMINISTRADOR',
        );
    }

    public function test_guardar_el_mismo_rol_conserva_las_fechas_y_no_registra_un_cambio(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(RolSistema::Gerencia);
        $fechaAnterior = '2026-10-19 14:30:00';
        DB::table('usuario_rol')->whereIn('user_id', $cuentas->pluck('id'))
            ->update(['fecha_asignacion' => $fechaAnterior]);
        Log::spy();

        $resultado = app(AsignarRolPersona::class)->asignar($autor, $cuentas['001'], RolSistema::Gerencia);

        $this->assertSame(['cambio' => false], $resultado);
        $this->comprobarRol($cuentas, RolSistema::Gerencia);
        foreach ($cuentas as $cuenta) {
            $this->assertDatabaseHas('usuario_rol', [
                'user_id' => $cuenta->id,
                'rol' => 'GERENCIA',
                'fecha_asignacion' => $fechaAnterior,
            ]);
        }
        Log::shouldNotHaveReceived('info');
    }

    #[TestWith([null])]
    #[TestWith(['GERENCIA'])]
    public function test_rechaza_cambiar_el_rol_propio_desde_el_id_de_la_otra_empresa(?string $rol): void
    {
        $cuentas = $this->persona(RolSistema::Administrador);
        Log::spy();

        $this->actingAs($cuentas['001'])->from(route('administracion.usuarios'))
            ->put(route('administracion.usuarios.rol', $cuentas['002']->id), ['rol' => $rol])
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasErrors(['rol' => 'No puedes cambiar tu propio rol.']);

        $this->comprobarRol($cuentas, RolSistema::Administrador);
        Log::shouldNotHaveReceived('info');
    }

    public function test_revierte_el_cambio_si_quitaria_al_ultimo_administrador(): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(RolSistema::Administrador);
        DB::table('usuario_rol')->where('user_id', $autor->id)->delete();
        Log::spy();

        try {
            app(AsignarRolPersona::class)->asignar($autor, $cuentas['001'], null);
            $this->fail('La operación debía rechazar el retiro del último administrador.');
        } catch (ValidationException $error) {
            $this->assertSame(['rol' => ['Debe quedar al menos una persona con rol Administrador.']], $error->errors());
        }

        $this->comprobarRol($cuentas, RolSistema::Administrador);
        Log::shouldNotHaveReceived('info');
    }

    /** @param array{rol?: string} $datos */
    #[TestWith([['rol' => 'OTRO']])]
    #[TestWith([[]])]
    public function test_el_rol_invalido_o_ausente_da_error_de_validacion_y_conserva_el_rol(array $datos): void
    {
        $autor = $this->administrador();
        $cuentas = $this->persona(RolSistema::Gerencia);
        Log::spy();

        $this->actingAs($autor)->from(route('administracion.usuarios'))
            ->put(route('administracion.usuarios.rol', $cuentas['001']->id), $datos)
            ->assertRedirectToRoute('administracion.usuarios')
            ->assertSessionHasErrors(['rol' => 'Elige un rol válido.']);

        $this->comprobarRol($cuentas, RolSistema::Gerencia);
        Log::shouldNotHaveReceived('info');
    }

    public function test_el_id_inexistente_da_404_y_no_registra_un_cambio(): void
    {
        $autor = $this->administrador();
        Log::spy();

        $this->actingAs($autor)->put(route('administracion.usuarios.rol', $autor->id + 1), ['rol' => 'GERENCIA'])
            ->assertNotFound();

        $this->assertDatabaseCount('usuario_rol', 1);
        Log::shouldNotHaveReceived('info');
    }

    private function administrador(): User
    {
        $autor = $this->crearPersonaUsuario('Autor', '11111111', ['001' => 'autor@ejemplo.test'])['001'];
        $this->asignarRol($autor->id, RolSistema::Administrador);

        return $autor;
    }

    /** @return Collection<string, User> */
    private function persona(?RolSistema $rol = null): Collection
    {
        $cuentas = $this->crearPersonaUsuario('Persona', '22222222', [
            '001' => 'persona@empresa-uno.test',
            '002' => 'persona@empresa-dos.test',
        ]);
        if ($rol !== null) {
            foreach ($cuentas as $cuenta) {
                $this->asignarRol($cuenta->id, $rol);
            }
        }

        return $cuentas;
    }

    /** @param Collection<string, User> $cuentas */
    private function comprobarRol(Collection $cuentas, ?RolSistema $rol): void
    {
        $asignaciones = DB::table('usuario_rol')->whereIn('user_id', $cuentas->pluck('id'))
            ->orderBy('user_id')
            ->get(['user_id', 'rol'])
            ->map(fn (object $asignacion): array => (array) $asignacion)
            ->all();
        $esperadas = $rol === null ? [] : $cuentas->map(
            fn (User $cuenta): array => ['user_id' => $cuenta->id, 'rol' => $rol->value],
        )->values()->all();

        $this->assertSame($esperadas, $asignaciones);
    }
}
