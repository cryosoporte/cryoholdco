<?php

namespace Tests\Feature\Informes;

use App\Mail\DocumentosContrato;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DocumentosContratoTest extends TestCase
{
    private const NUMERO_BASE = 'PE-CR-2026-000001-0';

    private string $raiz;

    private string $carpetaContrato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInformes();
        $this->crearContratoInformesPrueba();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'documentos-contrato-'.uniqid();
        $this->carpetaContrato = $this->raiz.DIRECTORY_SEPARATOR.self::NUMERO_BASE.'-ST-EJEMPLO ANA';
        mkdir($this->carpetaContrato, 0777, true);
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'RES-10.pdf', 'resultado diez');
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'RES-2.pdf', 'resultado dos');
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'Contrato.pdf', 'contrato');
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'Thumbs.db', 'basura de windows');
        mkdir($this->carpetaContrato.DIRECTORY_SEPARATOR.'subcarpeta');

        config([
            'laboratorio.raices' => ['001' => $this->raiz, '002' => null],
            'laboratorio.maximo_adjuntos_mb' => 20,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'array',
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->raiz);

        parent::tearDown();
    }

    public function test_exige_autenticacion(): void
    {
        Mail::fake();

        $this->getJson(route('informes.documentos', ['numero_base' => self::NUMERO_BASE]))->assertUnauthorized();
        $this->getJson(route('informes.documentos.ver', ['numero_base' => self::NUMERO_BASE, 'archivo' => 'Contrato.pdf']))->assertUnauthorized();
        $this->postJson(route('informes.documentos.enviar'), $this->datosEnvio())->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_lista_los_archivos_de_la_carpeta_del_contrato_sin_marcar_ninguno(): void
    {
        $this->listar()->assertOk()->assertJson([
            'archivos' => [
                ['nombre' => 'Contrato.pdf', 'tamano' => 8],
                ['nombre' => 'RES-2.pdf', 'tamano' => 13],
                ['nombre' => 'RES-10.pdf', 'tamano' => 14],
            ],
        ]);
    }

    public function test_lista_tambien_los_contratos_en_estado_des(): void
    {
        DB::table('ven_contratos')->update(['estado_contrato' => 'DES']);

        $this->listar()->assertOk()->assertJsonCount(3, 'archivos');
    }

    public function test_no_confunde_contratos_con_el_mismo_inicio_de_numero(): void
    {
        mkdir($this->raiz.DIRECTORY_SEPARATOR.self::NUMERO_BASE.'1-ST-OTRA PERSONA');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.self::NUMERO_BASE.'1-ST-OTRA PERSONA'.DIRECTORY_SEPARATOR.'Ajeno.pdf', 'ajeno');

        $this->listar()->assertOk()->assertJsonMissing(['nombre' => 'Ajeno.pdf']);
    }

    #[DataProvider('carpetasEscritasAMano')]
    public function test_reconoce_carpetas_con_espacios_sobrantes_en_el_nombre(string $numeroBase, string $nombreCarpeta): void
    {
        $this->crearContratoInformesPrueba(['numero_contrato' => $numeroBase.'-S', 'numero_base' => $numeroBase, 'identificacion1' => '55555555']);
        $carpeta = $this->raiz.DIRECTORY_SEPARATOR.$nombreCarpeta;
        mkdir($carpeta);
        file_put_contents($carpeta.DIRECTORY_SEPARATOR.'Informe.pdf', 'informe');

        $this->listar(['numero_base' => $numeroBase])->assertOk()->assertJson([
            'archivos' => [['nombre' => 'Informe.pdf', 'tamano' => 7]],
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function carpetasEscritasAMano(): array
    {
        return [
            'espacio al inicio' => ['PE-CR-2026-022064-1', ' PE-CR-2026-022064-1-ST-GALLARDO JIMENEZ'],
            'espacio invisible al inicio' => ['PE-CR-2026-022064-1', "\u{00A0}PE-CR-2026-022064-1-ST-GALLARDO JIMENEZ"],
            'espacio dentro del código' => ['PE-CR-2026-022089-1', 'PE-CR-2026- 022089-1-ST-DEACON SABLICH'],
            'espacio en lugar de guion' => ['16236', '16236 ST - CONTARDO MANRIQUE'],
            'letra Ñ en el nombre' => ['15280', '15280-ST-MUÑOZ CASTRO'],
        ];
    }

    public function test_un_numero_no_coincide_con_otro_que_lo_contiene(): void
    {
        $this->crearContratoInformesPrueba(['numero_contrato' => '9504-S', 'numero_base' => '9504', 'identificacion1' => '55555555']);
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'19504-ST-OTRO CLIENTE');
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'95040-ST-OTRO CLIENTE');
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'9504-ST-CABALLERO POLANCO');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'9504-ST-CABALLERO POLANCO'.DIRECTORY_SEPARATOR.'Propio.pdf', 'propio');

        $this->listar(['numero_base' => '9504'])->assertOk()->assertJson([
            'archivos' => [['nombre' => 'Propio.pdf', 'tamano' => 6]],
        ]);
    }

    public function test_reconoce_la_carpeta_aunque_cambien_las_mayusculas(): void
    {
        DB::table('ven_contratos')->update(['numero_base' => strtolower(self::NUMERO_BASE)]);

        $this->listar(['numero_base' => strtolower(self::NUMERO_BASE)])->assertOk()->assertJsonCount(3, 'archivos');
    }

    public function test_devuelve_404_si_el_contrato_no_tiene_carpeta(): void
    {
        $this->crearContratoInformesPrueba(['numero_contrato' => 'PE-CR-2026-999999-1', 'numero_base' => 'PE-CR-2026-999999-0', 'identificacion1' => '55555555']);

        $this->listar(['numero_base' => 'PE-CR-2026-999999-0'])
            ->assertNotFound()->assertJsonPath('message', 'Este contrato no tiene carpeta de documentos.');
    }

    public function test_devuelve_409_si_hay_mas_de_una_carpeta_con_archivos_para_el_contrato(): void
    {
        $duplicada = $this->raiz.DIRECTORY_SEPARATOR.self::NUMERO_BASE.'-ST-DUPLICADA';
        mkdir($duplicada);
        file_put_contents($duplicada.DIRECTORY_SEPARATOR.'Otro.pdf', 'otro');

        $this->listar()->assertStatus(409)
            ->assertJsonPath('message', 'Hay más de una carpeta para este contrato. Avisa a laboratorio.');
    }

    public function test_ignora_la_carpeta_duplicada_que_esta_vacia(): void
    {
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'PE-CR-2026- 000001-0-ST-EJEMPLO ANA');

        $this->listar()->assertOk()->assertJsonCount(3, 'archivos');
    }

    public function test_si_todas_las_carpetas_duplicadas_estan_vacias_lista_sin_archivos(): void
    {
        (new Filesystem)->cleanDirectory($this->carpetaContrato);
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'PE-CR-2026- 000001-0-ST-EJEMPLO ANA');

        $this->listar()->assertOk()->assertJson(['archivos' => []]);
    }

    public function test_devuelve_502_si_la_unidad_de_documentos_no_esta_disponible(): void
    {
        config(['laboratorio.raices.001' => $this->raiz.DIRECTORY_SEPARATOR.'no-existe']);

        $this->listar()->assertStatus(502)->assertJsonPath('message', 'No se pudo acceder a la unidad de documentos.');
    }

    public function test_cada_empresa_solo_ve_su_propia_raiz(): void
    {
        $this->crearContratoInformesPrueba(
            ['no_cia' => '002', 'numero_contrato' => 'LV-1', 'identificacion1' => '87654321'],
            ['nombre' => 'Familia Lazo'],
        );

        $this->listar([], '002')->assertStatus(502);

        $lazo = $this->raiz.DIRECTORY_SEPARATOR.'LAZO DE VIDA';
        mkdir($lazo.DIRECTORY_SEPARATOR.self::NUMERO_BASE.'-OTRA FAMILIA', 0777, true);
        config(['laboratorio.raices.002' => $lazo]);

        $this->listar([], '002')->assertOk()->assertJson(['archivos' => []]);
    }

    #[DataProvider('contratosFueraDeInformes')]
    public function test_devuelve_404_para_contratos_que_no_son_de_informes(string $caso): void
    {
        match ($caso) {
            'estado distinto de CON y DES' => DB::table('ven_contratos')->update(['estado_contrato' => 'PDE']),
            'sin registro de colecta' => DB::table('lab_colectas')->delete(),
            'otra empresa' => DB::table('ven_contratos')->update(['no_cia' => '999']),
        };
        $this->crearClienteConCorreoValido();
        Mail::fake();

        $this->listar()->assertNotFound();
        $this->enviar()->assertNotFound();
        $this->ver()->assertNotFound();

        Mail::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function contratosFueraDeInformes(): array
    {
        return [
            'estado distinto de CON y DES' => ['estado distinto de CON y DES'],
            'sin registro de colecta' => ['sin registro de colecta'],
            'otra empresa' => ['otra empresa'],
        ];
    }

    public function test_devuelve_403_si_el_usuario_no_tiene_empresa(): void
    {
        $sinEmpresa = UserFactory::new()->make(['id' => 1, 'empresa' => null]);

        $this->actingAs($sinEmpresa)->getJson(route('informes.documentos', ['numero_base' => self::NUMERO_BASE]))->assertForbidden();
        $this->actingAs($sinEmpresa)->getJson(route('informes.documentos.ver', ['numero_base' => self::NUMERO_BASE, 'archivo' => 'Contrato.pdf']))->assertForbidden();
        $this->actingAs($sinEmpresa)->postJson(route('informes.documentos.enviar'), $this->datosEnvio())->assertForbidden();
    }

    public function test_envia_solo_los_archivos_marcados_al_correo_del_cliente(): void
    {
        DB::table('ven_clientes')->update(['mail_contacto' => ' ana@example.com ']);
        Mail::fake();

        $this->enviar(['archivos' => ['Contrato.pdf', 'RES-10.pdf'], 'correo' => 'otro@example.com'])
            ->assertOk()->assertExactJson(['message' => 'Documentos enviados correctamente.']);

        Mail::assertSent(DocumentosContrato::class, function (DocumentosContrato $correo): bool {
            $this->assertTrue($correo->hasTo('ana@example.com'));
            $this->assertFalse($correo->hasTo('otro@example.com'));
            $this->assertCount(2, $correo->attachments());
            $correo->assertHasSubject('Documentos de tu contrato N.º '.self::NUMERO_BASE);
            $correo->assertHasAttachment(Attachment::fromPath($this->carpetaContrato.DIRECTORY_SEPARATOR.'Contrato.pdf')->as('Contrato.pdf'));
            $correo->assertHasAttachment(Attachment::fromPath($this->carpetaContrato.DIRECTORY_SEPARATOR.'RES-10.pdf')->as('RES-10.pdf'));

            return true;
        });
    }

    public function test_el_correo_sale_por_el_transporte_del_entorno(): void
    {
        config([
            'mail.mailers.sac_peru.transport' => 'array',
            'mail.mailers.sac_peru.from.address' => 'sac@example.com',
        ]);
        $this->app->instance('env', 'production');

        $this->enviar()->assertOk();

        $this->assertCount(1, Mail::mailer('sac_peru')->getSymfonyTransport()->messages());
        $this->assertCount(0, Mail::mailer('smtp')->getSymfonyTransport()->messages());
    }

    public function test_deja_registro_de_quien_envio_que_y_a_quien(): void
    {
        Mail::fake();
        Log::spy();

        $this->enviar(['archivos' => ['Contrato.pdf']])->assertOk();

        Log::shouldHaveReceived('info')->once()->with('Documentos de contrato enviados por correo.', Mockery::on(
            fn (array $contexto): bool => $contexto['empresa'] === '001'
                && $contexto['numero_base'] === self::NUMERO_BASE
                && $contexto['destinatario'] === 'ana@example.com'
                && $contexto['archivos'] === ['Contrato.pdf']
                && $contexto['autor'] !== '',
        ));
    }

    public function test_rechaza_archivos_que_no_estan_en_la_carpeta_del_contrato(): void
    {
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'secreto.txt', 'no debe salir');
        Mail::fake();

        foreach (['secreto.txt', '../secreto.txt', '..\\secreto.txt', 'subcarpeta', 'Thumbs.db', 'No-existe.pdf'] as $nombre) {
            $this->enviar(['archivos' => ['Contrato.pdf', $nombre]])
                ->assertUnprocessable()->assertJsonValidationErrors('archivos');
        }

        Mail::assertNothingSent();
    }

    public function test_exige_al_menos_un_archivo_y_los_datos_del_contrato(): void
    {
        Mail::fake();

        $this->enviar(['archivos' => []])->assertUnprocessable()->assertJsonValidationErrors([
            'archivos' => 'Selecciona al menos un archivo para enviar.',
        ]);
        $this->enviar(['numero_base' => null, 'identificacion' => null, 'archivos' => ['Contrato.pdf']])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'numero_base' => 'El cliente no tiene un número de contrato base.',
                'identificacion' => 'Falta la identificación del cliente.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_rechaza_envios_que_superan_el_tamano_maximo(): void
    {
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'Grande.pdf', str_repeat('a', 700 * 1024));
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'Grande-2.pdf', str_repeat('b', 700 * 1024));
        config(['laboratorio.maximo_adjuntos_mb' => 1]);
        Mail::fake();

        $this->enviar(['archivos' => ['Grande.pdf', 'Grande-2.pdf']])
            ->assertUnprocessable()->assertJsonValidationErrors('archivos');

        $this->enviar(['archivos' => ['Grande.pdf']])->assertOk();

        Mail::assertSent(DocumentosContrato::class, 1);
    }

    #[DataProvider('correosInvalidos')]
    public function test_devuelve_422_si_el_cliente_no_tiene_correo_valido(?string $correo): void
    {
        DB::table('ven_clientes')->update(['mail_contacto' => $correo]);
        Mail::fake();

        $this->enviar()->assertUnprocessable()->assertJsonValidationErrors([
            'documentos' => 'El cliente no tiene un correo de contacto válido registrado.',
        ]);

        Mail::assertNothingSent();
    }

    /** @return array<string, array{0: ?string}> */
    public static function correosInvalidos(): array
    {
        return [
            'sin correo' => [null],
            'solo espacios' => ['   '],
            'formato incorrecto' => ['sin-correo'],
            'varios destinatarios' => ['ana@example.com;otro@example.com'],
        ];
    }

    public function test_devuelve_404_si_la_identificacion_no_corresponde_al_contrato(): void
    {
        Mail::fake();

        $this->enviar(['identificacion' => '87654321'])->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_informa_el_fallo_del_proveedor_sin_confirmar_un_envio(): void
    {
        $this->app->instance('env', 'production');
        $exception = new RuntimeException('Fallo de transporte');
        Exceptions::fake();
        $mailer = Mockery::mock(Mailer::class);
        $pendiente = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('mailer')->once()->with('sac_peru')->andReturn($mailer);
        $mailer->shouldReceive('to')->once()->with('ana@example.com')->andReturn($pendiente);
        $pendiente->shouldReceive('send')->once()->with(Mockery::type(DocumentosContrato::class))->andThrow($exception);

        $this->enviar()->assertStatus(502)->assertExactJson([
            'message' => 'No se pudieron enviar los documentos. Intenta de nuevo más tarde.',
        ]);

        Exceptions::assertReported(fn (RuntimeException $reportada): bool => $reportada === $exception);
    }

    public function test_muestra_los_pdf_en_el_navegador_con_un_tipo_de_contenido_fijo(): void
    {
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'Falso.pdf', '<script>alert(1)</script>');

        $respuesta = $this->ver(['archivo' => 'Falso.pdf'])->assertOk();

        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', $respuesta->headers->get('Content-Disposition'));
        $this->assertSame('<script>alert(1)</script>', $respuesta->baseResponse->getFile()->getContent());
    }

    public function test_descarga_en_lugar_de_mostrar_los_archivos_que_no_son_pdf_ni_imagen(): void
    {
        file_put_contents($this->carpetaContrato.DIRECTORY_SEPARATOR.'Pagina.html', '<script>alert(1)</script>');

        $respuesta = $this->ver(['archivo' => 'Pagina.html'])->assertOk();

        $this->assertStringStartsWith('attachment;', $respuesta->headers->get('Content-Disposition'));
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_no_muestra_archivos_que_no_estan_en_la_carpeta_del_contrato(): void
    {
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'secreto.txt', 'no debe salir');

        foreach (['secreto.txt', '../secreto.txt', 'subcarpeta', 'Thumbs.db', 'No-existe.pdf'] as $nombre) {
            $this->ver(['archivo' => $nombre])->assertUnprocessable();
        }
    }

    private function crearClienteConCorreoValido(): void
    {
        DB::table('ven_clientes')->update(['mail_contacto' => 'ana@example.com']);
    }

    /** @return array{numero_base: string, identificacion: string, archivos: list<string>} */
    private function datosEnvio(): array
    {
        return ['numero_base' => self::NUMERO_BASE, 'identificacion' => '12345678', 'archivos' => ['Contrato.pdf']];
    }

    /** @param array<string, mixed> $parametros */
    private function listar(array $parametros = [], string $empresa = '001'): TestResponse
    {
        return $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => $empresa]))
            ->getJson(route('informes.documentos', ['numero_base' => self::NUMERO_BASE, ...$parametros]));
    }

    /** @param array<string, mixed> $parametros */
    private function ver(array $parametros = [], string $empresa = '001'): TestResponse
    {
        return $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => $empresa]))
            ->getJson(route('informes.documentos.ver', ['numero_base' => self::NUMERO_BASE, 'archivo' => 'Contrato.pdf', ...$parametros]));
    }

    /** @param array<string, mixed> $datos */
    private function enviar(array $datos = [], string $empresa = '001'): TestResponse
    {
        return $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => $empresa]))
            ->withSession(['_token' => 'token-de-prueba'])
            ->withHeader('X-CSRF-TOKEN', 'token-de-prueba')
            ->postJson(route('informes.documentos.enviar'), [...$this->datosEnvio(), ...$datos]);
    }
}
