<?php

namespace Tests\Feature\Informes;

use App\Http\Middleware\HandleInertiaRequests;
use App\Informes\FilasInformes;
use App\Mail\DocumentosContrato;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EnvioRegistradoTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInformes();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'envio-registrado-'.uniqid();
        mkdir($this->raiz);
        config([
            'laboratorio.raices' => ['001' => $this->raiz, '002' => null],
            'laboratorio.registrar_envio' => true,
        ]);

        $this->crearContratoInformesPrueba(
            ['numero_contrato' => 'BASE-1', 'numero_base' => 'BASE-1', 'identificacion1' => '11111111', 'identificacion2' => '22222222'],
            ['nombre' => 'Ana Madre', 'apaterno' => 'Pérez', 'celular_contacto' => '999111222'],
        );
        DB::table('ven_clientes')->insert([
            'no_cia' => '001', 'identificacion' => '22222222', 'nombre' => 'Luis Padre', 'nombre_corto' => 'Luis',
            'apaterno' => 'Gómez', 'mail_contacto' => 'luis@example.com', 'celular_contacto' => '999333444',
        ]);
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'BASE-1-FAMILIA');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'BASE-1-FAMILIA'.DIRECTORY_SEPARATOR.'A.pdf', 'a');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'BASE-1-FAMILIA'.DIRECTORY_SEPARATOR.'RHE-A.pdf', 'a');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'BASE-1-FAMILIA'.DIRECTORY_SEPARATOR.'OTRO.pdf', 'o');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->raiz);

        parent::tearDown();
    }

    public function test_el_envio_individual_marca_el_contrato_como_enviado_con_el_usuario(): void
    {
        Mail::fake();

        $this->enviarIndividual()->assertOk();

        $colecta = DB::table('lab_colectas')->where('numero_contrato', 'BASE-1')->first();
        $this->assertSame(1, (int) $colecta->flag_envio_correo);
        $this->assertSame('AGIUSTI', $colecta->usuario_envio);
        $this->assertNotNull($colecta->envio_fecha_correo);
        Mail::assertSent(DocumentosContrato::class, 1);
    }

    public function test_al_enviar_se_renueva_la_cache_y_el_contrato_pasa_a_enviado_en_el_listado(): void
    {
        Mail::fake();

        $this->consultar(['envio' => 'pendiente'])->assertOk()->assertJsonPath('props.resultados.total', 1);

        $this->enviarIndividual()->assertOk();
        $this->app->forgetInstance(FilasInformes::class);

        $this->consultar(['envio' => 'pendiente'])->assertOk()->assertJsonPath('props.resultados.total', 0);
        $this->consultar(['envio' => 'enviado'])->assertOk()
            ->assertJsonPath('props.resultados.data.0.numero_base', 'BASE-1')
            ->assertJsonPath('props.resultados.data.0.usuario_envio', 'AGIUSTI');
    }

    public function test_con_correo_de_prueba_el_mensaje_va_a_esa_direccion_y_no_al_cliente(): void
    {
        Mail::fake();
        config(['laboratorio.correo_prueba' => 'pruebas@cryoholdco.com']);

        $this->enviarIndividual()->assertOk();

        Mail::assertSent(DocumentosContrato::class, fn ($correo) => $correo->hasTo('pruebas@cryoholdco.com') && ! $correo->hasTo('ana@example.com'));
    }

    public function test_el_envio_masivo_marca_los_contratos_enviados(): void
    {
        Mail::fake();

        $this->actingAs($this->usuario())
            ->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')
            ->postJson(route('informes.documentos.enviar-masivo'), ['contratos' => [['numero_base' => 'BASE-1', 'identificacion' => '11111111']]])
            ->assertOk()->assertJsonPath('enviados', ['BASE-1']);

        $this->assertSame(1, (int) DB::table('lab_colectas')->where('numero_contrato', 'BASE-1')->value('flag_envio_correo'));
    }

    public function test_no_marca_nada_si_el_registro_esta_desactivado(): void
    {
        Mail::fake();
        config(['laboratorio.registrar_envio' => false]);

        $this->enviarIndividual()->assertOk();

        $this->assertNull(DB::table('lab_colectas')->where('numero_contrato', 'BASE-1')->value('flag_envio_correo'));
    }

    public function test_no_marca_el_contrato_si_el_correo_no_pudo_enviarse(): void
    {
        DB::table('ven_clientes')->where('identificacion', '11111111')->update(['mail_contacto' => null]);
        Mail::fake();

        $this->enviarIndividual()->assertUnprocessable();

        $this->assertNull(DB::table('lab_colectas')->where('numero_contrato', 'BASE-1')->value('flag_envio_correo'));
    }

    public function test_permite_ver_pero_no_enviar_un_contrato_sin_registro_en_lab_colectas_info(): void
    {
        $this->crearContratoInformesPrueba(
            ['numero_contrato' => 'SININFO-1', 'numero_base' => 'SININFO-1', 'identificacion1' => '55555555'],
            enInfo: false,
        );
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'SININFO-1-CARPETA');
        Mail::fake();

        $this->actingAs($this->usuario())
            ->getJson(route('informes.documentos', ['numero_base' => 'SININFO-1']))
            ->assertOk();

        $this->actingAs($this->usuario())
            ->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')
            ->postJson(route('informes.documentos.enviar-masivo'), ['contratos' => [['numero_base' => 'SININFO-1', 'identificacion' => '55555555']]])
            ->assertOk()->assertJsonPath('enviados', [])
            ->assertJsonPath('omitidos.0.motivo', 'Un servicio del contrato aún no está liberado; no se puede enviar el informe.');

        Mail::assertNothingSent();
    }

    public function test_el_detalle_de_documentos_trae_los_dos_titulares(): void
    {
        $this->actingAs($this->usuario())
            ->getJson(route('informes.documentos', ['numero_base' => 'BASE-1']))
            ->assertOk()
            ->assertJsonPath('titulares', [
                ['titular' => 1, 'nombre' => 'Ana Madre', 'celular' => '999111222', 'correo' => 'ana@example.com'],
                ['titular' => 2, 'nombre' => 'Luis Padre', 'celular' => '999333444', 'correo' => 'luis@example.com'],
            ]);
    }

    public function test_el_listado_trae_la_familia_con_los_apellidos_de_ambos_titulares(): void
    {
        $this->consultar()->assertOk()
            ->assertJsonPath('props.resultados.data.0.familia', 'Gómez Pérez')
            ->assertJsonPath('props.resultados.data.0.enviado', false);
    }

    public function test_el_listado_filtra_por_enviado_y_no_enviado_y_muestra_quien_envio(): void
    {
        $this->crearContratoInformesPrueba(['numero_contrato' => 'BASE-2', 'numero_base' => 'BASE-2', 'identificacion1' => '33333333'], ['nombre' => 'Otra', 'apaterno' => 'Ruiz']);
        DB::table('lab_colectas')->where('numero_contrato', 'BASE-2')->update([
            'flag_envio_correo' => 1, 'envio_fecha_correo' => '2026-11-20 00:00:00', 'usuario_envio' => 'DALVA',
        ]);

        $this->consultar(['envio' => 'enviado'])->assertOk()
            ->assertJsonPath('props.resultados.total', 1)
            ->assertJsonPath('props.resultados.data.0.numero_base', 'BASE-2')
            ->assertJsonPath('props.resultados.data.0.enviado', true)
            ->assertJsonPath('props.resultados.data.0.fecha_envio', '2026-11-20')
            ->assertJsonPath('props.resultados.data.0.usuario_envio', 'DALVA');

        $this->consultar(['envio' => 'pendiente'])->assertOk()
            ->assertJsonPath('props.resultados.total', 1)
            ->assertJsonPath('props.resultados.data.0.numero_base', 'BASE-1');

        $this->consultar(['envio' => 'otro'])->assertRedirect();
    }

    public function test_si_un_servicio_del_contrato_base_ya_se_envio_el_contrato_cuenta_como_enviado(): void
    {
        $this->crearContratoInformesPrueba(['numero_contrato' => 'B3-S', 'numero_base' => 'B3', 'identificacion1' => '33333333', 'servicio' => 'S', 'estado_contrato' => 'DES']);
        $this->crearContratoInformesPrueba(['numero_contrato' => 'B3-T', 'numero_base' => 'B3', 'identificacion1' => '33333333', 'servicio' => 'T', 'estado_contrato' => 'RET']);
        DB::table('lab_colectas')->where('numero_contrato', 'B3-T')->update([
            'flag_envio_correo' => 1, 'envio_fecha_correo' => '2024-01-10 00:00:00', 'usuario_envio' => 'AGIUSTI',
        ]);

        $this->consultar(['envio' => 'enviado'])->assertOk()
            ->assertJsonPath('props.resultados.total', 1)
            ->assertJsonPath('props.resultados.data.0.numero_base', 'B3')
            ->assertJsonPath('props.resultados.data.0.fecha_envio', '2024-01-10')
            ->assertJsonPath('props.resultados.data.0.usuario_envio', 'AGIUSTI');

        $this->consultar(['envio' => 'pendiente'])->assertOk()->assertJsonPath('props.resultados.total', 1);
    }

    public function test_solo_lista_servicios_de_sangre_y_tejido(): void
    {
        $this->crearContratoInformesPrueba(['numero_contrato' => 'OTRO-1', 'numero_base' => 'OTRO-1', 'identificacion1' => '44444444', 'servicio' => 'X']);

        $this->consultar()->assertOk()->assertJsonPath('props.resultados.total', 1);
    }

    private function usuario(): object
    {
        return UserFactory::new()->make(['id' => 1, 'empresa' => '001', 'codigo' => 'AGIUSTI']);
    }

    private function enviarIndividual(): TestResponse
    {
        return $this->actingAs($this->usuario())
            ->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')
            ->postJson(route('informes.documentos.enviar'), [
                'numero_base' => 'BASE-1', 'identificacion' => '11111111', 'archivos' => ['A.pdf'],
            ]);
    }

    /**
     * @param  array<string, string>  $parametros
     */
    private function consultar(array $parametros = []): TestResponse
    {
        $url = route('informes', $parametros);
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));

        return $this->actingAs($this->usuario())->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '']);
    }
}
