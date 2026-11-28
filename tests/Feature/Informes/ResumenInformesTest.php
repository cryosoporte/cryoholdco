<?php

namespace Tests\Feature\Informes;

use App\Http\Middleware\HandleInertiaRequests;
use App\Informes\FilasInformes;
use App\Informes\FiltrosInformes;
use App\Informes\ListadoInformes;
use App\Informes\ResumenInformes;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ResumenInformesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInformes();
        $this->travelTo('2026-11-27 12:00:00');
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']));
        config(['laboratorio.raices' => ['001' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'no-existe-resumen', '002' => null]]);

        // Cada servicio: [liberación de VEN_CONTRATOS, está en LAB_COLECTAS_INFO].
        $this->crearBase('A', '2026-11-17', ['S' => ['APTO', true], 'T' => ['APTO', true]]);
        $this->crearBase('B', '2026-10-13', ['S' => ['NO APTO', true], 'T' => ['APTO', true]], enviado: true);
        $this->crearBase('C', '2026-09-13', ['S' => ['APTO', true]]);
        $this->crearBase('D', '2026-07-30', ['S' => ['NO APTO', true], 'T' => ['NO APTO', true]]);
        $this->crearBase('E', '2026-12-05', ['S' => ['APTO', true]]); // colecta futura
        $this->crearBase('F', '2026-10-28', ['S' => ['APTO', true]]); // exactamente 30 días
        $this->crearBase('G', '2026-10-27', ['S' => ['APTO', true]]); // 31 días
        $this->crearBase('H', '2026-11-20', ['S' => ['APTO', true], 'T' => ['APTO', false]]); // tejido sin liberar
        $this->crearBase('I', '2026-11-01', ['S' => [null, false], 'T' => [null, false]]); // nada liberado
        $this->crearBase('J', '2026-08-01', ['S' => ['APTO', false]], enviado: true); // enviado sin liberar
    }

    public function test_el_resumen_cuenta_liberacion_antiguedad_y_resultados_sin_filtros(): void
    {
        $this->assertSame([
            'total' => 10,
            'liberacion' => ['liberado' => 7, 'no_liberado' => 3],
            'antiguedad' => ['0_30' => 4, '31_60' => 1, '61_90' => 1, '91_mas' => 1],
            'sangre' => ['apto' => 6, 'no_apto' => 2, 'no_liberado' => 2],
            'tejido' => ['apto' => 2, 'no_apto' => 1, 'no_liberado' => 2],
        ], $this->resumen(new FiltrosInformes));
    }

    public function test_los_conteos_reaccionan_a_los_filtros_activos(): void
    {
        $resumen = $this->resumen(new FiltrosInformes(envio: 'pendiente'));

        $this->assertSame(8, $resumen['total']);
        $this->assertSame(['liberado' => 6, 'no_liberado' => 2], $resumen['liberacion']);
        $this->assertSame(['apto' => 6, 'no_apto' => 1, 'no_liberado' => 1], $resumen['sangre']);
        $this->assertSame(['apto' => 1, 'no_apto' => 1, 'no_liberado' => 2], $resumen['tejido']);

        $liberados = $this->resumen(new FiltrosInformes(envio: 'pendiente', liberacion: 'liberado'));

        $this->assertSame(6, $liberados['total']);
        $this->assertSame(['0_30' => 2, '31_60' => 1, '61_90' => 1, '91_mas' => 1], $liberados['antiguedad']);
    }

    public function test_cada_tarjeta_cuenta_exactamente_lo_que_muestra_el_listado_al_pulsarla(): void
    {
        $combinaciones = [
            new FiltrosInformes,
            new FiltrosInformes(envio: 'pendiente'),
            new FiltrosInformes(envio: 'enviado'),
            new FiltrosInformes(liberacion: 'liberado'),
            new FiltrosInformes(envio: 'pendiente', liberacion: 'liberado', sangre: 'apto'),
            new FiltrosInformes(antiguedad: '0_30'),
            new FiltrosInformes(tejido: 'no_liberado'),
            new FiltrosInformes(buscar: 'cliente a'),
            new FiltrosInformes(buscar: 'a', envio: 'pendiente', antiguedad: '91_mas'),
        ];

        foreach ($combinaciones as $filtros) {
            $resumen = $this->resumen($filtros);

            $this->assertSame($this->total($filtros), $resumen['total'], 'total con '.json_encode($filtros));

            foreach ($this->tarjetas($resumen) as [$dimension, $valor, $cantidad]) {
                $conTarjeta = [...(array) $filtros, $dimension => $valor];

                $this->assertSame(
                    $this->total(new FiltrosInformes(...$conTarjeta)),
                    $cantidad,
                    "tarjeta {$dimension}={$valor} con ".json_encode($filtros),
                );
            }
        }
    }

    public function test_el_listado_incluye_los_contratos_sin_registro_en_lab_colectas_info_como_no_liberados(): void
    {
        $fila = collect($this->consultar(parametros: ['envio' => 'todos'])->assertOk()->json('props.resultados.data'))->keyBy('numero_base')['I'];

        $this->assertSame('NO LIBERADO', $fila['sangre']);
        $this->assertSame('NO LIBERADO', $fila['tejido']);
        $this->assertSame('AMBOS NO APTOS', $fila['estado']);
    }

    public function test_sin_filtro_de_envio_se_muestran_los_pendientes_y_todos_quita_el_filtro(): void
    {
        $this->consultar()->assertOk()
            ->assertJsonPath('props.envio', 'pendiente')
            ->assertJsonPath('props.resultados.total', 8);

        $this->consultar(parametros: ['envio' => 'todos'])->assertOk()
            ->assertJsonPath('props.envio', null)
            ->assertJsonPath('props.resultados.total', 10);

        $this->consultar(parametros: ['envio' => 'enviado'])->assertOk()
            ->assertJsonPath('props.resultados.total', 2);
    }

    public function test_el_filtro_de_antiguedad_solo_incluye_contratos_no_enviados(): void
    {
        $this->assertEqualsCanonicalizing(['A', 'F', 'H', 'I'], $this->bases(['envio' => 'todos', 'antiguedad' => '0_30']));
        $this->assertSame(['G'], $this->bases(['envio' => 'todos', 'antiguedad' => '31_60']));
        $this->assertSame(['C'], $this->bases(['envio' => 'todos', 'antiguedad' => '61_90']));
        $this->assertSame(['D'], $this->bases(['envio' => 'todos', 'antiguedad' => '91_mas']));
        $this->assertSame([], $this->bases(['envio' => 'enviado', 'antiguedad' => '91_mas']));
    }

    public function test_filtra_por_liberado_y_por_resultado_de_sangre_y_de_tejido(): void
    {
        $todos = ['envio' => 'todos'];

        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D', 'E', 'F', 'G'], $this->bases([...$todos, 'liberacion' => 'liberado']));
        $this->assertEqualsCanonicalizing(['H', 'I', 'J'], $this->bases([...$todos, 'liberacion' => 'no_liberado']));
        $this->assertEqualsCanonicalizing(['H', 'I'], $this->bases(['liberacion' => 'no_liberado']));
        $this->assertEqualsCanonicalizing(['I', 'J'], $this->bases([...$todos, 'sangre' => 'no_liberado']));
        $this->assertEqualsCanonicalizing(['H', 'I'], $this->bases([...$todos, 'tejido' => 'no_liberado']));
        $this->assertEqualsCanonicalizing(['B', 'D'], $this->bases([...$todos, 'sangre' => 'no_apto']));
        $this->assertSame(['D'], $this->bases([...$todos, 'tejido' => 'no_apto']));
        $this->assertEqualsCanonicalizing(['A', 'C', 'E', 'F', 'G'], $this->bases(['liberacion' => 'liberado', 'sangre' => 'apto']));
    }

    public function test_el_resumen_llega_en_la_misma_respuesta_que_el_listado(): void
    {
        $respuesta = $this->consultar()->assertOk()
            ->assertJsonPath('props.resultados.total', 8)
            ->assertJsonPath('props.resumen.total', 8)
            ->assertJsonPath('props.resumen.liberacion', ['liberado' => 6, 'no_liberado' => 2]);

        $this->assertArrayNotHasKey('deferredProps', $respuesta->json());
    }

    public function test_el_listado_y_el_resumen_usan_la_cache_unos_segundos_y_se_renuevan(): void
    {
        $this->assertContains('A', $this->bases(['envio' => 'pendiente']));

        DB::table('lab_colectas')->whereIn('numero_contrato', ['A-S', 'A-T'])->update(['flag_envio_correo' => 1]);

        // Una petición posterior, dentro de la ventana de caché, todavía ve el dato anterior.
        $this->app->forgetInstance(FilasInformes::class);
        $this->assertContains('A', $this->bases(['envio' => 'pendiente']));

        // Pasada la ventana, la siguiente petición lee Oracle de nuevo.
        $this->travel(FilasInformes::SEGUNDOS_EN_CACHE + 1)->seconds();
        $this->app->forgetInstance(FilasInformes::class);

        $this->assertNotContains('A', $this->bases(['envio' => 'pendiente']));
    }

    public function test_no_acepta_valores_de_filtro_desconocidos(): void
    {
        foreach (['antiguedad' => '10_20', 'sangre' => 'regular', 'tejido' => 'x', 'liberacion' => 'medio', 'envio' => 'quizas'] as $filtro => $valor) {
            $this->consultar(parametros: [$filtro => $valor])->assertRedirect()->assertSessionHasErrors($filtro);
        }
    }

    /**
     * @param  array<string, array{?string, bool}>  $servicios  servicio => [liberación, está en LAB_COLECTAS_INFO]
     */
    private function crearBase(string $base, string $fechaColecta, array $servicios, bool $enviado = false): void
    {
        foreach ($servicios as $servicio => [$liberacion, $enInfo]) {
            $this->crearContratoInformesPrueba(
                ['numero_contrato' => "{$base}-{$servicio}", 'numero_base' => $base, 'servicio' => $servicio, 'identificacion1' => "id-{$base}", 'liberacion' => $liberacion],
                ['nombre' => "Cliente {$base}", 'nombre_corto' => $base, 'apaterno' => $base],
                true,
                $enInfo,
            );
            DB::table('lab_colectas')->where('numero_contrato', "{$base}-{$servicio}")->update([
                'fecha_colecta' => $fechaColecta,
                'flag_envio_correo' => $enviado ? 1 : null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(FiltrosInformes $filtros): array
    {
        return app(ResumenInformes::class)->calcular('001', $filtros);
    }

    private function total(FiltrosInformes $filtros): int
    {
        return app(ListadoInformes::class)->consultar('001', $filtros)['resultados']->total();
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return list<array{string, string, int}>
     */
    private function tarjetas(array $resumen): array
    {
        $tarjetas = [];

        foreach (['liberacion', 'antiguedad', 'sangre', 'tejido'] as $dimension) {
            foreach ($resumen[$dimension] as $valor => $cantidad) {
                $tarjetas[] = [$dimension, $valor, $cantidad];
            }
        }

        return $tarjetas;
    }

    /**
     * @param  array<string, string>  $cabeceras
     * @param  array<string, string>  $parametros
     */
    private function consultar(array $cabeceras = [], array $parametros = []): TestResponse
    {
        $url = route('informes', $parametros);
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));

        return $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '', ...$cabeceras]);
    }

    /**
     * @param  array<string, string>  $parametros
     * @return list<string>
     */
    private function bases(array $parametros): array
    {
        return collect($this->consultar(parametros: $parametros)->assertOk()->json('props.resultados.data'))->pluck('numero_base')->all();
    }
}
