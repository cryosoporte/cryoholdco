<?php

namespace Tests;

use App\Http\Middleware\HandleInertiaRequests;
use App\Reportes\Fuentes\CatalogoFuentes;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use App\Reportes\Fuentes\TipoColumna;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PDO;

abstract class TestCase extends BaseTestCase
{
    /**
     * Escribe un .xlsx temporal con una fila por elemento de $filas (la primera
     * suele ser la de encabezados) y devuelve su ruta. Las fechas se escriben
     * con formato de fecha, como las guarda Excel. El archivo se borra al
     * terminar la prueba.
     *
     * @param  list<list<bool|DateTimeInterface|float|int|string|null>>  $filas
     */
    protected function crearXlsx(array $filas): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $estiloFecha = (new Style)->withFormat('dd/mm/yyyy hh:mm');

        $escritor = new Writer;
        $escritor->openToFile($ruta);

        foreach ($filas as $valores) {
            $escritor->addRow(new Row(array_map(
                fn (mixed $valor): Cell => Cell::fromValue($valor, $valor instanceof DateTimeInterface ? $estiloFecha : null),
                $valores,
            )));
        }

        $escritor->close();

        $this->beforeApplicationDestroyed(function () use ($ruta): void {
            @unlink($ruta);
            @unlink(substr($ruta, 0, -5));
        });

        return $ruta;
    }

    /**
     * Crea en SQLite la tabla maestra de fuentes (con la fila de Facturas
     * Oracle) y la tabla de cada fuente del catálogo, armada desde su
     * definición para que no se desalineen.
     */
    protected function prepararFuentes(): void
    {
        Schema::create('fuentes_datos', function (Blueprint $table): void {
            $table->string('codigo', 30)->primary();
            $table->string('nombre', 100);
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->string('actualizado_por', 100)->nullable();
            $table->unsignedInteger('filas')->nullable();
            $table->string('archivo', 255)->nullable();
        });

        DB::table('fuentes_datos')->insert([
            'codigo' => FuenteFacturasOracle::CODIGO,
            'nombre' => 'Facturas Oracle NetSuite',
        ]);

        foreach ((new CatalogoFuentes)->todas() as $fuente) {
            Schema::create($fuente->tabla(), function (Blueprint $table) use ($fuente): void {
                foreach ($fuente->columnas() as $columna) {
                    $definicion = match ($columna->tipo) {
                        TipoColumna::Entero => $table->bigInteger($columna->columna),
                        TipoColumna::Decimal => $table->decimal($columna->columna, $columna->precision, $columna->escala),
                        TipoColumna::Texto => $table->string($columna->columna, $columna->longitud),
                        TipoColumna::Fecha, TipoColumna::FechaHora => $table->dateTime($columna->columna),
                    };

                    $definicion->nullable(! $columna->obligatoria);
                }
            });
        }
    }

    protected function prepararPruebaComisiones(): void
    {
        Schema::create('prueba_comisiones', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_interno')->primary();
            $table->string('numero_documento', 20)->nullable();
            $table->dateTime('fecha_emision')->nullable();
            $table->decimal('importe', 14, 2)->nullable();
        });
    }

    protected function prepararSerologia(): void
    {
        $this->withoutVite();

        Schema::create('ven_contratos', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('numero_contrato');
            $table->string('numero_base');
            $table->string('identificacion1');
            $table->string('codigo_documento1')->nullable();
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
        Schema::create('lab_laboratorios_usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('identificacion', 15)->unique();
            $table->string('password');
            $table->string('estado', 1);
            $table->timestamps();
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
     * SQLite no tiene TRUNC ni SYSDATE, que usa la lista «Tomas del día» del mostrador.
     * Se registra TRUNC y se simula SYSDATE como una columna de las tomas con la fecha indicada.
     */
    protected function simularSysdateOracle(string $hoy): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('TRUNC', fn (?string $fecha): ?string => $fecha === null ? null : substr($fecha, 0, 10), 1);

        Schema::table('ope_tomas_serologicas', function (Blueprint $table) use ($hoy): void {
            $table->dateTime('sysdate')->default($hoy);
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
