<?php

namespace Tests;

use App\Administracion\RolSistema;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FuenteCarga;
use App\Reportes\Fuentes\CatalogoFuentes;
use App\Reportes\Fuentes\FuenteConColumnasCalculadas;
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
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('sqlite')->create('usuario_rol', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->string('rol', 30);
            $table->dateTime('fecha_asignacion')->useCurrent();
            $table->primary(['user_id', 'rol']);
        });
    }

    protected function asignarRol(int $usuarioId, RolSistema $rol): void
    {
        DB::connection('sqlite')->table('usuario_rol')->insert(['user_id' => $usuarioId, 'rol' => $rol->value]);
    }

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
            $columnas = $fuente instanceof FuenteConColumnasCalculadas
                ? [...$fuente->columnas(), ...$fuente->columnasCalculadas()]
                : $fuente->columnas();

            Schema::create($fuente->tabla(), function (Blueprint $table) use ($columnas): void {
                foreach ($columnas as $columna) {
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

    protected function prepararCargasFuentes(): void
    {
        Schema::create((new FuenteCarga)->getTable(), function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_fuente', 30);
            $table->char('estado', 1);
            $table->string('archivo', 255);
            $table->string('ruta_archivo', 150)->nullable();
            $table->unsignedInteger('filas')->nullable();
            $table->string('mensaje', 4000)->nullable();
            $table->string('cargado_por', 100);
            $table->string('usuario_id', 100);
            $table->dateTime('fecha_recepcion');
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
        });
    }

    /**
     * Crea en SQLite las tablas de configuración de comisiones que mantiene
     * el área en Oracle: metas, tramos y línea de cada servicio.
     */
    protected function prepararComisiones(): void
    {
        Schema::create('com_meta', function (Blueprint $table): void {
            $table->integer('meta_id')->primary();
            $table->string('rol_cod', 60);
            $table->string('atribucion', 20);
            $table->string('linea_cod', 20);
            $table->char('periodicidad', 1);
            $table->string('tipo_cod', 20);
            $table->string('base_tramo', 10);
            $table->decimal('minimo', 14, 2);
            $table->dateTime('vig_desde');
            $table->dateTime('vig_hasta')->nullable();
            $table->char('estado', 1);
            $table->string('origen', 60)->nullable();
            $table->string('observacion', 300)->nullable();
        });
        Schema::create('com_meta_tramo', function (Blueprint $table): void {
            $table->integer('meta_id');
            $table->integer('nro_tramo');
            $table->decimal('desde', 14, 2);
            $table->decimal('hasta', 14, 2)->nullable();
            $table->decimal('valor', 14, 4);
        });
        Schema::create('com_servicio_linea', function (Blueprint $table): void {
            $table->string('servicio', 100)->primary();
            $table->string('linea_cod', 20);
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('codigo_netsuite', 150)->nullable();
            $table->string('rol_netsuite', 60)->nullable();
        });
    }

    protected function prepararLiquidaciones(): void
    {
        Schema::create('com_liq', function (Blueprint $table): void {
            $table->id();
            $table->string('periodo', 7);
            $table->char('tipo', 1);
            $table->string('estado', 20);
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->decimal('total', 14, 2);
            $table->unsignedInteger('carga_id')->nullable();
            $table->text('desglose');
            $table->dateTime('fecha_creacion');
        });
        DB::statement("CREATE UNIQUE INDEX com_liq_activa_ux ON com_liq (periodo) WHERE estado <> 'RECHAZADA'");

        Schema::create('com_liq_detalle', function (Blueprint $table): void {
            $table->foreignId('liq_id')->constrained('com_liq');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('meta_id');
            $table->string('nombre', 150);
            $table->string('codigo_netsuite', 150);
            $table->string('rol_netsuite', 100);
            $table->string('linea', 30);
            $table->string('atribucion', 20);
            $table->string('tipo_meta', 20);
            $table->string('base_tramo', 20);
            $table->decimal('minimo', 14, 2);
            $table->integer('cantidad');
            $table->decimal('monto', 14, 2);
            $table->decimal('tramo_desde', 14, 2)->nullable();
            $table->decimal('tramo_hasta', 14, 2)->nullable();
            $table->decimal('tramo_valor', 14, 4)->nullable();
            $table->decimal('comision', 14, 2);
            $table->decimal('adelanto', 14, 2)->nullable();
            $table->decimal('pendiente', 14, 2)->nullable();
            $table->decimal('a_pagar', 14, 2);
            $table->primary(['liq_id', 'user_id', 'meta_id']);
        });
        Schema::create('com_liq_contrato', function (Blueprint $table): void {
            $table->foreignId('liq_id')->constrained('com_liq');
            $table->unsignedInteger('user_id');
            $table->string('linea', 30);
            $table->string('numero_contrato', 50);
            $table->string('servicio', 200)->nullable();
            $table->date('fecha_emision');
            $table->decimal('monto', 14, 2);
            $table->integer('filas');
            $table->index(['liq_id', 'user_id', 'linea']);
        });
        Schema::create('com_liq_evento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liq_id')->constrained('com_liq');
            $table->string('accion', 20);
            $table->unsignedInteger('user_id');
            $table->string('nombre', 150);
            $table->string('empresa', 3);
            $table->string('comentario', 1000)->nullable();
            $table->dateTime('fecha');
        });
        Schema::create('com_liq_envio', function (Blueprint $table): void {
            $table->foreignId('liq_id')->constrained('com_liq');
            $table->unsignedInteger('user_id');
            $table->string('correo', 150);
            $table->char('estado', 1);
            $table->integer('intentos')->default(0);
            $table->dateTime('fecha_envio')->nullable();
            $table->string('error', 1000)->nullable();
            $table->primary(['liq_id', 'user_id']);
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
