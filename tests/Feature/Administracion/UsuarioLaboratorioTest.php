<?php

namespace Tests\Feature\Administracion;

use App\Models\UsuarioLaboratorio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioLaboratorioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSerologia();
    }

    public function test_guarda_la_contrasena_con_hash_y_no_la_expone(): void
    {
        $acceso = UsuarioLaboratorio::factory()->create([
            'identificacion' => '20330025213',
            'password' => 'secreto123',
        ]);

        $guardada = DB::table('lab_laboratorios_usuarios')->where('identificacion', '20330025213')->value('password');

        $this->assertNotSame('secreto123', $guardada);
        $this->assertTrue(Hash::check('secreto123', $guardada));
        $this->assertArrayNotHasKey('password', $acceso->toArray());
    }

    public function test_distingue_un_acceso_activo_de_uno_inactivo(): void
    {
        $this->assertTrue(UsuarioLaboratorio::factory()->make()->estaActivo());
        $this->assertFalse(UsuarioLaboratorio::factory()->inactivo()->make()->estaActivo());
    }

    public function test_puede_ingresar_solo_si_esta_activo_y_tiene_sedes_activas(): void
    {
        $activo = UsuarioLaboratorio::factory()->make(['identificacion' => '20330025213']);
        $inactivo = UsuarioLaboratorio::factory()->inactivo()->make(['identificacion' => '20330025213']);

        $this->assertFalse($activo->puedeIngresar(), 'Sin sedes no puede ingresar.');

        $this->crearSedePrueba('20330025213');
        $this->assertTrue($activo->puedeIngresar());
        $this->assertFalse($inactivo->puedeIngresar());

        DB::table('lab_laboratorios_sedes')->update(['estado' => 'I']);
        $this->assertFalse($activo->puedeIngresar(), 'Con todas sus sedes inactivas no puede ingresar.');
    }

    public function test_no_guarda_token_de_recordarme(): void
    {
        $acceso = UsuarioLaboratorio::factory()->create();

        $acceso->setRememberToken('token');

        $this->assertNull($acceso->getRememberToken());
        $this->assertSame([], $acceso->getDirty());
    }
}
