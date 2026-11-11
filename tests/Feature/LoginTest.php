<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private const MENSAJE_RECHAZO = 'Credenciales incorrectas o sin acceso a la compañía.';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('empresa');
            $table->integer('flag_activo');
            $table->rememberToken();
        });
        Schema::create('cor_companias', function (Blueprint $table): void {
            $table->string('codigo_compania');
            $table->string('nombre_corto');
        });
        DB::table('cor_companias')->insert([
            ['codigo_compania' => '001', 'nombre_corto' => 'Criocord'],
            ['codigo_compania' => '002', 'nombre_corto' => 'Lazo de Vida'],
        ]);
        DB::table('users')->insert([
            'name' => 'Ana Operaciones', 'email' => 'Ana@Criocord.pe',
            'password' => Hash::make('secreta'), 'empresa' => '001', 'flag_activo' => 1,
        ]);
    }

    public function test_inicia_sesion_sin_distinguir_mayusculas_en_el_correo_y_guarda_la_empresa(): void
    {
        $this->post(route('login.store'), [
            'email' => 'ana@criocord.pe', 'password' => 'secreta', 'empresa' => '001',
        ])
            ->assertRedirectToRoute('home')
            ->assertSessionHas('empresa_nombre', 'Criocord');

        $this->assertAuthenticated();
    }

    /**
     * @param  array<string, string>  $cambios
     */
    #[DataProvider('accesosRechazados')]
    public function test_rechaza_el_acceso_sin_revelar_el_motivo_ni_devolver_la_contrasena(array $cambios, int $flagActivo = 1): void
    {
        DB::table('users')->update(['flag_activo' => $flagActivo]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'ana@criocord.pe', 'password' => 'secreta', 'empresa' => '001', ...$cambios,
        ])
            ->assertRedirectToRoute('login')
            ->assertSessionHasErrors(['email' => self::MENSAJE_RECHAZO])
            ->assertSessionHasInput('email')
            ->assertSessionHasInput('empresa');

        $this->assertArrayNotHasKey('password', session('_old_input', []));
        $this->assertGuest();
    }

    /** @return array<string, array{0: array<string, string>, 1?: int}> */
    public static function accesosRechazados(): array
    {
        return [
            'contraseña incorrecta' => [['password' => 'otra']],
            'correo inexistente' => [['email' => 'nadie@criocord.pe']],
            'otra empresa' => [['empresa' => '002']],
            'usuario inactivo' => [[], 0],
        ];
    }

    public function test_exige_correo_valido_contrasena_y_empresa(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['email' => 'no-es-correo'])
            ->assertRedirectToRoute('login')
            ->assertSessionHasErrors(['email', 'password', 'empresa']);

        $this->assertGuest();
    }
}
