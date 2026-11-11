<?php

namespace Tests;

use App\Http\Middleware\HandleInertiaRequests;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected function prepararSerologia(): void
    {
        $this->withoutVite();

        Schema::create('ven_contratos', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('numero_contrato');
            $table->string('numero_base');
            $table->string('identificacion1');
            $table->string('codigo_documento1');
            $table->string('servicio');
            $table->date('fecha_contrato');
            $table->date('fecha_nacimiento')->nullable();
            $table->string('contrato_lp')->nullable();
        });
        Schema::create('ven_clientes', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('identificacion');
            $table->string('nombre');
            $table->string('nombre_corto');
        });
        Schema::create('ope_tomas_serologicas', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('numero_contrato_base');
            $table->integer('numero_toma');
            $table->dateTime('fecha_toma')->nullable();
            $table->dateTime('fecha_resultado')->nullable();
            $table->string('laboratorio_toma')->nullable();
            $table->string('sede_toma')->nullable();
        });
        Schema::create('lab_laboratorios_sedes', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('identificacion');
            $table->string('codigo_sede');
            $table->string('descripcion_sede');
            $table->string('direccion_sede')->nullable();
            $table->string('ciudad');
            $table->string('estado');
        });
        Schema::create('cxp_proveedores', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('identificacion');
            $table->string('alias');
        });
    }

    /**
     * Prepara las tablas y funciones de Oracle que usa el listado de Serología.
     */
    protected function prepararListadoSerologia(): void
    {
        $this->prepararSerologia();

        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $pdo->sqliteCreateFunction('TO_DATE', fn (string $fecha): string => CarbonImmutable::createFromFormat('!d/m/Y', $fecha)->toDateString(), 2);
        $pdo->sqliteCreateFunction('TO_CHAR', fn (?string $fecha): ?string => $fecha === null ? null : substr($fecha, 0, 10), 2);

        Schema::table('ven_clientes', function (Blueprint $table): void {
            $table->string('celular_contacto')->nullable();
        });
        Schema::table('ven_contratos', function (Blueprint $table): void {
            $table->string('numero_prospecto')->nullable();
            $table->integer('confirm_nac')->nullable();
            $table->string('vendedor')->nullable();
        });
        Schema::create('ven_prospectos', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('numero_prospecto');
            $table->date('fecha_parto')->nullable();
        });
        Schema::create('lab_colectas', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('numero_contrato');
            $table->date('fecha_nacimiento')->nullable();
            $table->string('serologia')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('empresa');
            $table->string('identificacion');
            $table->string('nombres');
        });
    }

    /**
     * @param  array<string, string|int>  $parametros
     */
    protected function consultarListado(array $parametros = []): TestResponse
    {
        $url = route('serologia', $parametros);
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));

        return $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
        ]);
    }

    protected function crearContratoPrueba(bool $conToma = true): void
    {
        DB::table('ven_clientes')->insert([
            'no_cia' => '001', 'identificacion' => '12345678',
            'nombre' => 'Ana Ejemplo', 'nombre_corto' => 'Ana',
        ]);
        DB::table('ven_contratos')->insert([
            'no_cia' => '001', 'numero_contrato' => 'PE-CR-2026-000001-1',
            'numero_base' => 'PE-CR-2026-000001-0', 'identificacion1' => '12345678',
            'codigo_documento1' => 'DI', 'servicio' => 'S',
            'fecha_contrato' => '2026-10-01', 'fecha_nacimiento' => '2026-11-10',
            'contrato_lp' => 'LIMA',
        ]);

        if ($conToma) {
            DB::table('ope_tomas_serologicas')->insert([
                'no_cia' => '001', 'numero_contrato_base' => 'PE-CR-2026-000001-0', 'numero_toma' => 1,
            ]);
        }
    }

    protected function crearSedePrueba(string $laboratorio = '20330025213', string $sede = '01'): void
    {
        DB::table('cxp_proveedores')->insertOrIgnore([
            'no_cia' => '001', 'identificacion' => $laboratorio, 'alias' => 'Laboratorio de prueba',
        ]);
        DB::table('lab_laboratorios_sedes')->insert([
            'no_cia' => '001', 'identificacion' => $laboratorio, 'codigo_sede' => $sede,
            'descripcion_sede' => 'Sede '.$sede, 'ciudad' => 'LIMA', 'estado' => 'A',
        ]);
    }
}
