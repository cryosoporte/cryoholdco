<?php

namespace Tests\Feature\Reportes;

use App\Jobs\ImportarFuenteJob;
use App\Models\FuenteCarga;
use App\Reportes\Fuentes\ColumnaFuente;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use App\Reportes\ImportarFuente;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Database\Seeders\FuenteCargaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class CargasFuentesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['reportes.cargas_asincronas' => true]);
        $this->withoutVite();
        $this->prepararFuentes();
        $this->prepararCargasFuentes();
        Storage::fake('local');
        Queue::fake();

        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001', 'name' => 'Usuario de prueba']));
    }

    public function test_la_carga_publica_el_resultado_y_limpia_el_archivo_temporal(): void
    {
        $this->subir($this->archivoFacturas())
            ->assertRedirectToRoute('reportes.fuentes')
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('cargaFuenteRecibida', 'Facturas Oracle NetSuite');

        $carga = FuenteCarga::query()->sole();
        $this->assertSame(FuenteCarga::PENDIENTE, $carga->estado);
        $this->assertDatabaseCount('fuente_facturas_oracle', 0);
        Storage::disk('local')->assertExists($carga->ruta_archivo);
        Queue::assertPushed(ImportarFuenteJob::class, fn (ImportarFuenteJob $job): bool => $job->cargaId === $carga->id
            && $job->connection === 'redis_reportes');
        $this->get(route('reportes.fuentes'))
            ->assertInertia(fn (Assert $page): Assert => $page->where('fuentes.0.ultima_carga.estado', FuenteCarga::PENDIENTE));

        $job = Queue::pushed(ImportarFuenteJob::class)->first();
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        Storage::disk('local')->assertMissing($carga->ruta_archivo);
        $this->assertDatabaseHas('fuentes_cargas', [
            'id' => $carga->id, 'estado' => FuenteCarga::COMPLETADA, 'filas' => 2, 'ruta_archivo' => null,
        ]);
        $this->assertDatabaseCount('fuente_facturas_oracle', 2);
        $this->get(route('reportes.fuentes'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fuentes.0.filas', 2)
                ->where('fuentes.0.actualizado_por', 'Usuario de prueba')
                ->where('fuentes.0.ultima_carga.estado', FuenteCarga::COMPLETADA)
                ->where('fuentes.0.ultima_carga.filas', 2));
    }

    public function test_la_bitacora_guarda_las_fechas_en_hora_de_lima_como_la_maestra(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 15:32:00', 'UTC'));

        $this->subir($this->archivoFacturas())->assertSessionHasNoErrors();
        app()->call([Queue::pushed(ImportarFuenteJob::class)->first(), 'handle']);

        $this->assertDatabaseHas('fuentes_cargas', [
            'estado' => FuenteCarga::COMPLETADA,
            'fecha_recepcion' => '2026-11-17 10:32:00',
            'fecha_inicio' => '2026-11-17 10:32:00',
            'fecha_fin' => '2026-11-17 10:32:00',
        ]);
        $this->assertDatabaseHas('fuentes_datos', ['fecha_actualizacion' => '2026-11-17 10:32:00']);
    }

    public function test_un_archivo_invalido_mantiene_los_datos_y_la_fecha_anteriores(): void
    {
        DB::table('fuente_facturas_oracle')->insert(['id_interno' => 99]);
        DB::table('fuentes_datos')->update(['fecha_actualizacion' => '2026-11-17 10:32:00']);
        $this->subir($this->archivoFacturas(['ID interno']))->assertRedirectToRoute('reportes.fuentes');
        $carga = FuenteCarga::query()->sole();

        app()->call([Queue::pushed(ImportarFuenteJob::class)->first(), 'handle']);

        $this->assertDatabaseHas('fuente_facturas_oracle', ['id_interno' => 99]);
        $this->assertDatabaseHas('fuentes_datos', ['fecha_actualizacion' => '2026-11-17 10:32:00']);
        $this->assertDatabaseHas('fuentes_cargas', ['id' => $carga->id, 'estado' => FuenteCarga::FALLIDA]);
        Storage::disk('local')->assertMissing($carga->ruta_archivo);
        $this->get(route('reportes.fuentes'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fuentes.0.ultima_carga.estado', FuenteCarga::FALLIDA)
                ->where('fuentes.0.ultima_carga.mensaje', fn (string $mensaje): bool => str_starts_with($mensaje, 'Faltan columnas')));
    }

    public function test_otro_usuario_ve_el_fallo_sin_acceder_a_los_detalles_del_archivo(): void
    {
        $this->seed(FuenteCargaSeeder::class);
        $this->actingAs(UserFactory::new()->make(['id' => 2, 'empresa' => '002']));

        $this->get(route('reportes.fuentes'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fuentes.0.ultima_carga.estado', FuenteCarga::FALLIDA)
                ->where('fuentes.0.ultima_carga.mensaje', FuenteCarga::ERROR_IMPORTACION));
    }

    public function test_rechaza_otra_carga_mientras_la_actual_esta_pendiente_o_en_proceso(): void
    {
        $carga = FuenteCarga::factory()->create();
        $mensaje = 'Ya hay una carga en proceso para esta fuente.';

        $this->subir($this->archivoFacturas())->assertSessionHasErrors(['archivo' => $mensaje]);
        $this->assertTrue($carga->iniciar());
        $this->subir($this->archivoFacturas())->assertSessionHasErrors(['archivo' => $mensaje]);

        $this->assertDatabaseCount('fuentes_cargas', 1);
        Queue::assertNothingPushed();
        $this->get(route('reportes.fuentes'))
            ->assertInertia(fn (Assert $page): Assert => $page->where('fuentes.0.ultima_carga.estado', FuenteCarga::EN_PROCESO));
    }

    public function test_un_error_de_redis_no_deja_la_fuente_bloqueada(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Redis no disponible'));

        $this->subir($this->archivoFacturas())
            ->assertSessionHasErrors(['archivo' => 'No se pudo preparar la importación. Intenta nuevamente.']);

        $this->assertDatabaseHas('fuentes_cargas', ['estado' => FuenteCarga::FALLIDA, 'ruta_archivo' => null]);
        $this->assertSame([], Storage::disk('local')->allFiles('fuentes'));
        $this->assertDatabaseCount('fuente_facturas_oracle', 0);
    }

    public function test_si_el_despacho_falla_despues_de_que_el_worker_empezo_la_carga_sigue_su_curso(): void
    {
        Bus::shouldReceive('dispatch')->once()->andReturnUsing(function (ImportarFuenteJob $job): never {
            FuenteCarga::query()->findOrFail($job->cargaId)->iniciar();

            throw new RuntimeException('Timeout de Redis después de encolar');
        });

        $this->subir($this->archivoFacturas())
            ->assertRedirectToRoute('reportes.fuentes')
            ->assertSessionHasNoErrors();

        $carga = FuenteCarga::query()->sole();
        $this->assertSame(FuenteCarga::EN_PROCESO, $carga->estado);
        Storage::disk('local')->assertExists($carga->ruta_archivo);
    }

    public function test_el_scheduler_recupera_cargas_vencidas_y_limpia_temporales_finalizados(): void
    {
        $pendiente = FuenteCarga::factory()->create(['fecha_recepcion' => FuenteCarga::ahora()->subMinutes(31)]);
        $interrumpida = FuenteCarga::factory()->create([
            'estado' => FuenteCarga::EN_PROCESO,
            'fecha_inicio' => FuenteCarga::ahora()->subMinutes(18),
        ]);
        $vigente = FuenteCarga::factory()->create([
            'estado' => FuenteCarga::EN_PROCESO,
            'fecha_inicio' => FuenteCarga::ahora()->subMinutes(3),
        ]);
        $completada = FuenteCarga::factory()->create(['estado' => FuenteCarga::COMPLETADA]);

        foreach ([$pendiente, $interrumpida, $vigente, $completada] as $carga) {
            Storage::disk('local')->put($carga->ruta_archivo, 'archivo de prueba');
        }

        $this->artisan('reportes:recuperar-cargas')->assertSuccessful();

        foreach ([$pendiente, $interrumpida] as $carga) {
            $this->assertSame(FuenteCarga::FALLIDA, $carga->fresh()->estado);
            Storage::disk('local')->assertMissing($carga->ruta_archivo);
        }

        $this->assertSame(FuenteCarga::EN_PROCESO, $vigente->fresh()->estado);
        Storage::disk('local')->assertExists($vigente->ruta_archivo);
        $this->assertSame(FuenteCarga::COMPLETADA, $completada->fresh()->estado);
        Storage::disk('local')->assertMissing($completada->ruta_archivo);
    }

    public function test_un_job_que_perdio_su_reserva_no_puede_publicar_la_fuente(): void
    {
        DB::table('fuente_facturas_oracle')->insert(['id_interno' => 99]);
        $carga = FuenteCarga::factory()->create(['estado' => FuenteCarga::FALLIDA]);
        $archivo = $this->archivoFacturas();

        $this->assertThrows(
            fn (): int => app(ImportarFuente::class)->importar(new FuenteFacturasOracle, $archivo->getRealPath(), 'Usuario', 'facturas.xlsx', $carga),
            RuntimeException::class,
            'La carga dejó de estar activa',
        );

        $this->assertDatabaseCount('fuente_facturas_oracle', 1);
        $this->assertDatabaseHas('fuente_facturas_oracle', ['id_interno' => 99]);
        $this->assertDatabaseHas('fuentes_datos', ['fecha_actualizacion' => null]);
    }

    public function test_un_timeout_no_revierte_un_estado_que_ya_se_completo(): void
    {
        $carga = FuenteCarga::factory()->create(['estado' => FuenteCarga::COMPLETADA]);
        $job = new ImportarFuenteJob($carga->id, $carga->ruta_archivo);

        $job->failed(new RuntimeException('Worker interrumpido'));

        $this->assertSame(FuenteCarga::COMPLETADA, $carga->fresh()->estado);
    }

    private function subir(UploadedFile $archivo): TestResponse
    {
        return $this->post(route('reportes.fuentes.cargar', 'facturas_oracle'), ['archivo' => $archivo]);
    }

    /** @param list<string>|null $encabezados */
    private function archivoFacturas(?array $encabezados = null): UploadedFile
    {
        $encabezados ??= array_map(
            fn (ColumnaFuente $columna): string => $columna->encabezado,
            (new FuenteFacturasOracle)->columnas(),
        );

        $ruta = $this->crearXlsx([$encabezados, [113688], [115953]]);

        return UploadedFile::fake()->createWithContent('facturas.xlsx', file_get_contents($ruta));
    }
}
