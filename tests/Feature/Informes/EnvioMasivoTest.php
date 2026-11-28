<?php

namespace Tests\Feature\Informes;

use App\Mail\DocumentosContrato;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EnvioMasivoTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInformes();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'envio-masivo-'.uniqid();
        mkdir($this->raiz);
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

        $this->postJson(route('informes.documentos.enviar-masivo'), ['contratos' => [['numero_base' => 'A', 'identificacion' => '1']]])
            ->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_devuelve_403_si_el_usuario_no_tiene_empresa(): void
    {
        Mail::fake();

        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => null]))
            ->postJson(route('informes.documentos.enviar-masivo'), ['contratos' => [['numero_base' => 'A', 'identificacion' => '1']]])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_valida_los_contratos_recibidos(): void
    {
        Mail::fake();
        $demasiados = array_map(fn (int $n): array => ['numero_base' => "B{$n}", 'identificacion' => (string) $n], range(1, 11));

        $this->enviar([])->assertUnprocessable()->assertJsonValidationErrors(['contratos' => 'Selecciona al menos un contrato.']);
        $this->enviar($demasiados)->assertUnprocessable()->assertJsonValidationErrors('contratos');
        $this->enviar([['numero_base' => '', 'identificacion' => '']])->assertUnprocessable()->assertJsonValidationErrors([
            'contratos.0.numero_base', 'contratos.0.identificacion',
        ]);

        Mail::assertNothingSent();
    }

    public function test_envia_a_cada_cliente_todos_los_archivos_de_la_carpeta_de_su_contrato(): void
    {
        $this->crearContratoConCarpeta('BASE-1', '11111111', 'ana@example.com', ['RHE-A.pdf', 'RMB-B.pdf', 'RSC-C.pdf']);
        $this->crearContratoConCarpeta('BASE-2', '22222222', 'berta@example.com', ['REPORTE-D.pdf']);
        Mail::fake();

        $this->enviar([$this->contrato('BASE-1', '11111111'), $this->contrato('BASE-2', '22222222')])
            ->assertOk()->assertExactJson(['enviados' => ['BASE-1', 'BASE-2'], 'omitidos' => []]);

        Mail::assertSent(DocumentosContrato::class, 2);
        Mail::assertSent(DocumentosContrato::class, fn (DocumentosContrato $correo): bool => $correo->hasTo('ana@example.com') && count($correo->attachments()) === 3);
        Mail::assertSent(DocumentosContrato::class, fn (DocumentosContrato $correo): bool => $correo->hasTo('berta@example.com') && count($correo->attachments()) === 1);
    }

    public function test_envia_solo_los_archivos_predeterminados_de_la_carpeta(): void
    {
        $this->crearContratoConCarpeta('BASE-1', '11111111', 'ana@example.com', ['rhe-informe.pdf', 'Reporte final.pdf', 'FACTURA.pdf', 'contrato.pdf']);
        $this->crearContratoConCarpeta('BASE-2', '22222222', 'berta@example.com', ['FACTURA.pdf']);
        Mail::fake();

        $respuesta = $this->enviar([$this->contrato('BASE-1', '11111111'), $this->contrato('BASE-2', '22222222')])->assertOk();

        $this->assertSame(['BASE-1'], $respuesta->json('enviados'));
        $this->assertSame('Ningún archivo de la carpeta es de los que se envían por defecto.', $respuesta->json('omitidos.0.motivo'));
        Mail::assertSent(DocumentosContrato::class, fn (DocumentosContrato $correo): bool => $correo->hasTo('ana@example.com') && count($correo->attachments()) === 2);
    }

    public function test_omite_con_su_motivo_los_contratos_que_no_se_pueden_enviar_y_sigue_con_los_demas(): void
    {
        $this->crearContratoConCarpeta('BUENO', '11111111', 'ana@example.com', ['RHE-A.pdf']);
        $this->crearContratoConCarpeta('SINCORREO', '22222222', null, ['RMB-B.pdf']);
        $this->crearContratoConCarpeta('VACIO', '33333333', 'carla@example.com', []);
        $this->crearContratoConCarpeta('DUPLICADO', '44444444', 'diana@example.com', ['RSC-C.pdf']);
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'DUPLICADO-OTRA');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'DUPLICADO-OTRA'.DIRECTORY_SEPARATOR.'REPORTE-D.pdf', 'otro');
        $this->crearContratoConCarpeta('PESADO', '55555555', 'elena@example.com', ['RPU-E.pdf']);
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'PESADO-CARPETA'.DIRECTORY_SEPARATOR.'RPU-E.pdf', str_repeat('x', 2 * 1024 * 1024));
        config(['laboratorio.maximo_adjuntos_mb' => 1]);
        DB::table('ven_clientes')->where('identificacion', '11111111')->update(['mail_contacto' => 'ana@example.com']);
        $this->crearContratoInformesPrueba(['numero_contrato' => 'NOCARPETA', 'numero_base' => 'NOCARPETA', 'identificacion1' => '66666666']);
        Mail::fake();

        $respuesta = $this->enviar([
            $this->contrato('BUENO', '11111111'),
            $this->contrato('SINCORREO', '22222222'),
            $this->contrato('VACIO', '33333333'),
            $this->contrato('DUPLICADO', '44444444'),
            $this->contrato('PESADO', '55555555'),
            $this->contrato('NOCARPETA', '66666666'),
            $this->contrato('AJENO', '77777777'),
        ])->assertOk();

        $this->assertSame(['BUENO'], $respuesta->json('enviados'));
        $this->assertEqualsCanonicalizing(
            [
                'SINCORREO' => 'El cliente no tiene un correo de contacto válido registrado.',
                'VACIO' => 'La carpeta del contrato no tiene archivos para enviar.',
                'DUPLICADO' => 'Hay más de una carpeta para este contrato. Avisa a laboratorio.',
                'PESADO' => 'Los archivos superan los 1 MB permitidos por correo. Envía menos archivos a la vez.',
                'NOCARPETA' => 'Este contrato no tiene carpeta de documentos.',
                'AJENO' => 'El contrato no está en el listado de Informes de tu empresa.',
            ],
            collect($respuesta->json('omitidos'))->pluck('motivo', 'numero_base')->all(),
        );

        Mail::assertSent(DocumentosContrato::class, 1);
    }

    public function test_no_envia_dos_veces_la_misma_carpeta_al_mismo_cliente(): void
    {
        $this->crearContratoConCarpeta('BASE-1', '11111111', 'ana@example.com', ['RHE-A.pdf']);
        $this->crearContratoInformesPrueba(['numero_contrato' => 'BASE-1-T', 'numero_base' => 'BASE-1', 'identificacion1' => '11111111', 'servicio' => 'T']);
        Mail::fake();

        $this->enviar([$this->contrato('BASE-1', '11111111'), $this->contrato('BASE-1', '11111111')])
            ->assertOk()->assertExactJson(['enviados' => ['BASE-1'], 'omitidos' => []]);

        Mail::assertSent(DocumentosContrato::class, 1);
    }

    public function test_no_envia_si_la_identificacion_no_es_la_del_cliente_del_contrato(): void
    {
        $this->crearContratoConCarpeta('BASE-1', '11111111', 'ana@example.com', ['RHE-A.pdf']);
        Mail::fake();

        $this->enviar([$this->contrato('BASE-1', '99999999')])
            ->assertOk()->assertJsonPath('enviados', [])
            ->assertJsonPath('omitidos.0.motivo', 'El contrato no está en el listado de Informes de tu empresa.');

        Mail::assertNothingSent();
    }

    public function test_no_envia_contratos_de_otra_empresa(): void
    {
        $this->crearContratoInformesPrueba(['no_cia' => '002', 'numero_contrato' => 'LV-1', 'numero_base' => 'LV-1', 'identificacion1' => '88888888']);
        mkdir($this->raiz.DIRECTORY_SEPARATOR.'LV-1-FAMILIA');
        file_put_contents($this->raiz.DIRECTORY_SEPARATOR.'LV-1-FAMILIA'.DIRECTORY_SEPARATOR.'RHE-A.pdf', 'a');
        Mail::fake();

        $this->enviar([$this->contrato('LV-1', '88888888')])
            ->assertOk()->assertJsonPath('enviados', []);

        Mail::assertNothingSent();
    }

    public function test_informa_como_omitido_el_contrato_cuando_el_proveedor_de_correo_falla(): void
    {
        $this->app->instance('env', 'production');
        $this->crearContratoConCarpeta('BASE-1', '11111111', 'ana@example.com', ['RHE-A.pdf']);
        Exceptions::fake();
        $mailer = Mockery::mock(Mailer::class);
        $pendiente = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('mailer')->once()->with('sac_peru')->andReturn($mailer);
        $mailer->shouldReceive('to')->once()->with('ana@example.com')->andReturn($pendiente);
        $pendiente->shouldReceive('send')->once()->andThrow(new RuntimeException('Fallo de transporte'));

        $this->enviar([$this->contrato('BASE-1', '11111111')])
            ->assertOk()
            ->assertJsonPath('enviados', [])
            ->assertJsonPath('omitidos.0', ['numero_base' => 'BASE-1', 'motivo' => 'El proveedor de correo no pudo enviar el mensaje.']);

        Exceptions::assertReported(RuntimeException::class);
    }

    /**
     * @param  list<string>  $archivos
     */
    private function crearContratoConCarpeta(string $numeroBase, string $identificacion, ?string $correo, array $archivos): void
    {
        $this->crearContratoInformesPrueba(
            ['numero_contrato' => $numeroBase, 'numero_base' => $numeroBase, 'identificacion1' => $identificacion],
            ['nombre' => "Cliente {$numeroBase}", 'nombre_corto' => $numeroBase, 'mail_contacto' => $correo],
        );

        $carpeta = $this->raiz.DIRECTORY_SEPARATOR.$numeroBase.'-CARPETA';
        mkdir($carpeta);

        foreach ($archivos as $archivo) {
            file_put_contents($carpeta.DIRECTORY_SEPARATOR.$archivo, "contenido de {$archivo}");
        }
    }

    /** @return array{numero_base: string, identificacion: string} */
    private function contrato(string $numeroBase, string $identificacion): array
    {
        return ['numero_base' => $numeroBase, 'identificacion' => $identificacion];
    }

    /**
     * @param  list<array{numero_base: string, identificacion: string}>  $contratos
     */
    private function enviar(array $contratos, string $empresa = '001'): TestResponse
    {
        return $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => $empresa]))
            ->withSession(['_token' => 'token-de-prueba'])
            ->withHeader('X-CSRF-TOKEN', 'token-de-prueba')
            ->postJson(route('informes.documentos.enviar-masivo'), ['contratos' => $contratos]);
    }
}
