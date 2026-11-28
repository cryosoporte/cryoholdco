<?php

namespace Tests\Feature\Informes;

use App\Http\Middleware\HandleInertiaRequests;
use App\Informes\LiberacionContrato;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListadoInformesTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInformes();
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']));

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'listado-informes-'.uniqid();
        mkdir($this->raiz);
        config(['laboratorio.raices' => ['001' => $this->raiz, '002' => null]]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->raiz);

        parent::tearDown();
    }

    public function test_exige_autenticacion(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('informes'))->assertRedirect(route('login'));
    }

    public function test_devuelve_403_si_el_usuario_no_tiene_empresa(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => null]))
            ->get(route('informes'))->assertForbidden();
    }

    public function test_lista_solo_contratos_con_y_des_de_sangre_y_tejido_con_colecta_de_la_empresa_del_usuario(): void
    {
        $this->crearServicio('CON-1', 'S', '11111111', 'Ana Contratada');
        $this->crearServicio('DES-1', 'S', '22222222', 'Berta Desvinculada', ['estado_contrato' => 'DES']);
        $this->crearServicio('PDE-1', 'S', '33333333', 'Carla Pendiente', ['estado_contrato' => 'PDE']);
        $this->crearServicio('OTRO-1', 'A', '44444444', 'Diana Otro Servicio');
        $this->crearServicio('SINCOL-1', 'S', '55555555', 'Elena Sin Colecta', [], conColecta: false);
        $this->crearServicio('AJENA-1', 'S', '66666666', 'Fabiola Otra Empresa', ['no_cia' => '002']);

        $respuesta = $this->consultarInformes()->assertOk()
            ->assertJsonPath('component', 'informes')
            ->assertJsonPath('props.resultados.total', 2);

        $this->assertEqualsCanonicalizing(['CON-1', 'DES-1'], $this->bases($respuesta));
    }

    public function test_lista_tambien_los_contratos_sin_registro_en_lab_colectas_info_como_no_liberados(): void
    {
        $this->crearServicio('REGISTRADO-1', 'S', '11111111', 'Ana Registrada');
        $this->crearServicio('SOLO-TEJIDO-1', 'S', '22222222', 'Berta Parcial', enInfo: false);
        $this->crearServicio('SOLO-TEJIDO-1', 'T', '22222222', 'Berta Parcial');
        $this->crearServicio('SINREGISTRO-1', 'S', '33333333', 'Carla Sin Registro', enInfo: false);

        $respuesta = $this->consultarInformes()->assertOk()->assertJsonPath('props.resultados.total', 3);

        $this->assertEqualsCanonicalizing(['REGISTRADO-1', 'SOLO-TEJIDO-1', 'SINREGISTRO-1'], $this->bases($respuesta));

        $filas = collect($respuesta->json('props.resultados.data'))->keyBy('numero_base');
        $this->assertSame('NO LIBERADO', $filas['SINREGISTRO-1']['sangre']);
        $this->assertSame(['NO LIBERADO', 'APTO'], [$filas['SOLO-TEJIDO-1']['sangre'], $filas['SOLO-TEJIDO-1']['tejido']]);
    }

    public function test_un_contrato_base_con_sangre_y_tejido_aparece_una_sola_vez(): void
    {
        $this->crearServicio('PE-CR-2026-000001-1', 'S', '12345678', 'Ana Ejemplo');
        $this->crearServicio('PE-CR-2026-000001-1', 'T', '12345678', 'Ana Ejemplo');

        $this->consultarInformes()->assertOk()->assertJsonPath('props.resultados.total', 1);
    }

    public function test_cada_fila_trae_las_columnas_principales(): void
    {
        $this->crearServicio('PE-CR-2026-000001-1', 'S', '12345678', 'Ana Ejemplo', ['liberacion' => 'APTO'], apellido: 'Medina', enInfo: true);
        $this->crearServicio('PE-CR-2026-000001-1', 'T', '12345678', 'Ana Ejemplo', ['liberacion' => 'NO APTO'], enInfo: false);
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'PE-CR-2026-000001-1-ST-EJEMPLO ANA');

        $this->consultarInformes()->assertOk()->assertJsonPath('props.resultados.data.0', [
            'numero_base' => 'PE-CR-2026-000001-1',
            'estado' => 'UNO NO ES APTO',
            'sangre' => 'APTO',
            'tejido' => 'NO LIBERADO',
            'familia' => 'Medina',
            'fecha_colecta' => '2026-10-20',
            'enviado' => false,
            'fecha_envio' => null,
            'usuario_envio' => null,
            'identificacion' => '12345678',
            'nombre' => 'Ana Ejemplo',
            'mail_contacto' => 'ana@example.com',
            'tiene_carpeta' => true,
        ]);
    }

    /**
     * @param  array<string, ?string>  $liberaciones  servicio => liberación de VEN_CONTRATOS
     */
    #[DataProvider('estados')]
    public function test_el_estado_sale_de_la_liberacion_de_los_servicios(array $liberaciones, string $esperado): void
    {
        foreach ($liberaciones as $servicio => $liberacion) {
            $this->crearServicio('BASE-1', $servicio, '12345678', 'Ana', ['liberacion' => $liberacion]);
        }

        $this->consultarInformes()->assertOk()->assertJsonPath('props.resultados.data.0.estado', $esperado);
    }

    /** @return array<string, array{array<string, ?string>, string}> */
    public static function estados(): array
    {
        return [
            'ambos aptos' => [['S' => 'APTO', 'T' => 'APTO'], 'AMBOS APTOS'],
            'ambos no aptos' => [['S' => 'NO APTO', 'T' => 'NO APTO'], 'AMBOS NO APTOS'],
            'ambos nulos' => [['S' => null, 'T' => null], 'AMBOS NO APTOS'],
            'uno no apto' => [['S' => 'APTO', 'T' => 'NO APTO'], 'UNO NO ES APTO'],
            'uno nulo' => [['S' => 'APTO', 'T' => null], 'UNO NO ES APTO'],
            'solo sangre apto' => [['S' => 'APTO'], 'APTO'],
            'solo tejido nulo' => [['T' => null], 'NO APTO'],
        ];
    }

    public function test_sangre_y_tejido_indican_la_liberacion_solo_si_el_contrato_esta_en_lab_colectas_info(): void
    {
        $this->crearServicio('BASE-1', 'S', '12345678', 'Ana', ['liberacion' => 'APTO'], enInfo: true);
        $this->crearServicio('BASE-1', 'T', '12345678', 'Ana', ['liberacion' => 'APTO'], enInfo: false);
        $this->crearServicio('BASE-2', 'S', '87654321', 'Berta', ['liberacion' => 'NO APTO'], enInfo: true);
        $this->crearServicio('BASE-3', 'S', '11223344', 'Carla', ['liberacion' => null], enInfo: true);

        $filas = collect($this->consultarInformes()->assertOk()->json('props.resultados.data'))->keyBy('numero_base');

        $this->assertSame(['APTO', 'NO LIBERADO'], [$filas['BASE-1']['sangre'], $filas['BASE-1']['tejido']]);
        $this->assertSame(['NO APTO', null], [$filas['BASE-2']['sangre'], $filas['BASE-2']['tejido']]);
        $this->assertSame('NO APTO', $filas['BASE-3']['sangre']);
        $this->assertSame(LiberacionContrato::NO_LIBERADO, LiberacionContrato::servicio(true, false, 'APTO'));
    }

    public function test_la_familia_lleva_el_apellido_del_titular_2_y_luego_el_del_titular_1(): void
    {
        DB::table('ven_clientes')->insert([
            'no_cia' => '001', 'identificacion' => '22222222', 'nombre' => 'Luis Gómez', 'nombre_corto' => 'Luis', 'apaterno' => 'Gómez',
        ]);
        $this->crearServicio('BASE-1', 'S', '12345678', 'Ana Pérez', ['identificacion2' => '22222222'], apellido: 'Pérez');
        $this->crearServicio('BASE-2', 'S', '87654321', 'Berta Ruiz', apellido: 'Ruiz');

        $filas = collect($this->consultarInformes()->assertOk()->json('props.resultados.data'))->pluck('familia', 'numero_base');

        $this->assertSame('Gómez Pérez', $filas['BASE-1']);
        $this->assertSame('Ruiz', $filas['BASE-2']);
    }

    #[DataProvider('busquedas')]
    public function test_busca_por_nombre_dni_base_o_familia_sin_distinguir_mayusculas(string $buscar): void
    {
        $this->crearServicio('PE-CR-2026-000001-1', 'S', '12345678', 'Ana Ejemplo', apellido: 'Medina');
        $this->crearServicio('PE-CR-2026-000002-1', 'S', '87654321', 'Berta Otra', apellido: 'Lara');

        $this->assertSame(['PE-CR-2026-000001-1'], $this->bases($this->consultarInformes(['buscar' => $buscar])));
    }

    /** @return array<string, array{string}> */
    public static function busquedas(): array
    {
        return [
            'por nombre' => ['ana ejem'],
            'por dni' => ['1234'],
            'por base' => ['2026-000001'],
            'por familia' => ['medina'],
        ];
    }

    public function test_pagina_de_20_en_20_y_conserva_los_filtros_en_los_enlaces(): void
    {
        foreach (range(1, 25) as $numero) {
            $this->crearServicio(sprintf('CON-%02d', $numero), 'S', (string) (10000000 + $numero), "Cliente {$numero}");
        }

        $this->consultarInformes(['envio' => 'pendiente'])->assertOk()
            ->assertJsonPath('props.resultados.total', 25)
            ->assertJsonPath('props.resultados.last_page', 2)
            ->assertJsonCount(20, 'props.resultados.data')
            ->assertJsonPath('props.resultados.next_page_url', fn (string $url): bool => str_contains($url, 'envio=pendiente'));
    }

    public function test_indica_que_contratos_tienen_carpeta_con_una_sola_lectura_de_la_unidad(): void
    {
        $this->crearServicio('15280', 'S', '11111111', 'Ana Con Carpeta');
        $this->crearServicio('9504', 'S', '22222222', 'Berta Sin Carpeta');
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'15280-ST-MUÑOZ CASTRO');
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'19504-ST-OTRO CLIENTE');

        $respuesta = $this->consultarInformes()->assertOk()->assertJsonPath('props.unidad_disponible', true);

        $this->assertSame(
            ['9504' => false, '15280' => true],
            collect($respuesta->json('props.resultados.data'))->pluck('tiene_carpeta', 'numero_base')->sortKeys()->all(),
        );
    }

    public function test_lista_los_contratos_aunque_la_unidad_de_documentos_no_este_disponible(): void
    {
        $this->crearServicio('CON-1', 'S', '11111111', 'Ana Contratada');
        config(['laboratorio.raices.001' => $this->raiz.DIRECTORY_SEPARATOR.'no-existe']);

        $respuesta = $this->consultarInformes()->assertOk()
            ->assertJsonPath('props.unidad_disponible', false)
            ->assertJsonPath('props.resultados.total', 1);

        $this->assertNull($respuesta->json('props.resultados.data.0.tiene_carpeta'));
    }

    public function test_no_acepta_filtrar_por_un_envio_desconocido(): void
    {
        $this->consultarInformes(['envio' => 'otro'])->assertRedirect()->assertSessionHasErrors('envio');
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function crearServicio(string $base, string $servicio, string $identificacion, string $nombre, array $cambios = [], bool $conColecta = true, ?string $apellido = null, bool $enInfo = true): void
    {
        $this->crearContratoInformesPrueba(
            ['numero_contrato' => "{$base}-{$servicio}", 'numero_base' => $base, 'servicio' => $servicio, 'identificacion1' => $identificacion, ...$cambios],
            ['nombre' => $nombre, 'nombre_corto' => $nombre, 'apaterno' => $apellido],
            $conColecta,
            $enInfo,
        );

    }

    /**
     * @param  array<string, string|int>  $parametros
     */
    private function consultarInformes(array $parametros = []): TestResponse
    {
        $url = route('informes', $parametros);
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));

        return $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
        ]);
    }

    /**
     * @return list<string>
     */
    private function bases(TestResponse $respuesta): array
    {
        return collect($respuesta->json('props.resultados.data'))->pluck('numero_base')->all();
    }
}
