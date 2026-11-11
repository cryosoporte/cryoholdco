<?php

namespace Tests\Feature;

use App\Mail\RecordatorioSerologia;
use Database\Factories\UserFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RecordatorioSerologiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSerologia();

        Schema::table('ven_clientes', function (Blueprint $table): void {
            $table->string('mail_contacto')->nullable();
        });
        Schema::create('lab_colectas', function (Blueprint $table): void {
            $table->string('no_cia');
            $table->string('numero_contrato');
            $table->string('serologia')->nullable();
        });
    }

    public function test_exige_autenticacion_para_enviar_recordatorios(): void
    {
        Mail::fake();

        $this->postJson(route('serologia.recordatorio'), $this->datosRecordatorio())->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_envia_al_correo_guardado_y_no_al_destinatario_del_navegador(): void
    {
        $this->crearClienteConCorreo(' ana@example.com ');
        Mail::fake();

        $this->enviar(['correo' => 'otro@example.com', 'no_cia' => '999'])
            ->assertOk()->assertExactJson(['message' => 'Recordatorio enviado correctamente.']);

        Mail::assertSent(RecordatorioSerologia::class, fn (RecordatorioSerologia $correo): bool => $correo->hasTo('ana@example.com') && $correo->nombre === 'Ana Ejemplo'
        );
        Mail::assertSentCount(1);
    }

    #[DataProvider('correosInvalidos')]
    public function test_devuelve_422_si_el_cliente_no_tiene_correo_valido(?string $correo): void
    {
        $this->crearClienteConCorreo($correo);
        Mail::fake();

        $this->enviar()->assertUnprocessable()->assertJsonValidationErrors([
            'recordatorio' => 'El cliente no tiene un correo de contacto válido registrado.',
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

    public function test_devuelve_404_para_un_contrato_de_otra_compania(): void
    {
        $this->crearClienteConCorreo();
        Mail::fake();

        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '999']))
            ->postJson(route('serologia.recordatorio'), $this->datosRecordatorio())
            ->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_no_utiliza_el_correo_del_mismo_dni_en_otra_compania(): void
    {
        $this->crearClienteConCorreo(null);
        DB::table('ven_clientes')->insert([
            'no_cia' => '999', 'identificacion' => '12345678', 'nombre' => 'Otra persona',
            'nombre_corto' => 'Otra', 'mail_contacto' => 'otro@example.com',
        ]);
        Mail::fake();

        $this->enviar()->assertUnprocessable()->assertJsonValidationErrors('recordatorio');

        Mail::assertNothingSent();
    }

    public function test_devuelve_403_si_el_usuario_no_tiene_compania(): void
    {
        Mail::fake();

        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => null]))
            ->postJson(route('serologia.recordatorio'), $this->datosRecordatorio())->assertForbidden();

        Mail::assertNothingSent();
    }

    /** @param array<string, string> $cambios */
    #[DataProvider('contratosFueraDelListado')]
    public function test_devuelve_404_para_contratos_fuera_del_listado(array $cambios): void
    {
        $this->crearClienteConCorreo();
        DB::table('ven_contratos')->update($cambios);
        Mail::fake();

        $this->enviar()->assertNotFound();

        Mail::assertNothingSent();
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function contratosFueraDelListado(): array
    {
        return [
            'otro servicio' => [['servicio' => 'X']],
            'contrato anterior a 2026' => [['fecha_contrato' => '2025-12-31']],
            'otra base' => [['numero_base' => 'OTRA-BASE']],
        ];
    }

    public function test_devuelve_404_si_la_identificacion_no_corresponde_al_contrato(): void
    {
        $this->crearClienteConCorreo();
        Mail::fake();

        $this->enviar(['identificacion' => '87654321'])->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_devuelve_422_si_faltan_los_datos_del_contrato(): void
    {
        Mail::fake();

        $this->enviar(['numero_base' => null, 'identificacion' => null])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'numero_base' => 'El cliente no tiene un número de contrato base.',
                'identificacion' => 'Falta la identificación del cliente.',
            ]);

        Mail::assertNothingSent();
    }

    #[DataProvider('fechasDeMuestra')]
    public function test_devuelve_422_si_la_muestra_ya_fue_tomada_o_recibida(string $campo): void
    {
        $this->crearClienteConCorreo();
        DB::table('ope_tomas_serologicas')->update([$campo => '2026-11-10 10:00:00']);
        Mail::fake();

        $this->enviar()->assertUnprocessable()->assertJsonValidationErrors([
            'recordatorio' => 'La muestra ya fue tomada o recibida. Actualiza los resultados.',
        ]);

        Mail::assertNothingSent();
    }

    /** @return array<string, array{0: string}> */
    public static function fechasDeMuestra(): array
    {
        return ['tomada' => ['fecha_toma'], 'recibida' => ['fecha_resultado']];
    }

    public function test_devuelve_422_si_laboratorio_ya_registro_la_serologia(): void
    {
        $this->crearClienteConCorreo();
        DB::table('lab_colectas')->insert([
            'no_cia' => '001', 'numero_contrato' => 'PE-CR-2026-000001-1', 'serologia' => 'S',
        ]);
        Mail::fake();

        $this->enviar()->assertUnprocessable()->assertJsonValidationErrors('recordatorio');

        Mail::assertNothingSent();
    }

    public function test_ignora_las_muestras_de_otra_compania_con_la_misma_base(): void
    {
        $this->crearClienteConCorreo();
        DB::table('ope_tomas_serologicas')->insert([
            'no_cia' => '999', 'numero_contrato_base' => 'PE-CR-2026-000001-0',
            'numero_toma' => 1, 'fecha_toma' => '2026-11-10 10:00:00',
        ]);
        Mail::fake();

        $this->enviar()->assertOk();

        Mail::assertSent(RecordatorioSerologia::class, 1);
    }

    public function test_informa_el_fallo_del_proveedor_sin_confirmar_un_envio(): void
    {
        $this->crearClienteConCorreo();
        $exception = new RuntimeException('Fallo de transporte');
        Exceptions::fake();
        $pendiente = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with('ana@example.com')->andReturn($pendiente);
        $pendiente->shouldReceive('send')->once()->with(Mockery::type(RecordatorioSerologia::class))
            ->andThrow($exception);

        $this->enviar()->assertStatus(502)->assertExactJson([
            'message' => 'No se pudo enviar el recordatorio. Intenta de nuevo más tarde.',
        ]);

        Exceptions::assertReported(fn (RuntimeException $reportada): bool => $reportada === $exception);
    }

    public function test_la_plantilla_incluye_el_nombre_y_enlace_al_portal(): void
    {
        $correo = new RecordatorioSerologia('Ana Ejemplo');

        $correo->assertHasSubject('Recordatorio de toma de muestra para serología');
        $correo->assertSeeInHtml('Ana Ejemplo');
        $correo->assertSeeInHtml('<a href="'.e(route('clientes.index')).'"', false);
        $correo->assertSeeInHtml('Consultar mi serología');
        $correo->assertDontSeeInHtml('<pre', false);
        $correo->assertSeeInText('tienes pendiente tu toma de muestra para serología');
    }

    public function test_la_plantilla_escapa_el_html_del_nombre(): void
    {
        $correo = new RecordatorioSerologia('<script>alert(1)</script>');

        $correo->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $correo->assertSeeInHtml('&lt;script&gt;', false);
    }

    private function crearClienteConCorreo(?string $correo = 'ana@example.com'): void
    {
        $this->crearContratoPrueba();
        DB::table('ven_clientes')->update(['mail_contacto' => $correo]);
    }

    /** @return array{numero_base: string, identificacion: string} */
    private function datosRecordatorio(): array
    {
        return ['numero_base' => 'PE-CR-2026-000001-0', 'identificacion' => '12345678'];
    }

    /** @param array<string, mixed> $datos */
    private function enviar(array $datos = []): TestResponse
    {
        return $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']))
            ->postJson(route('serologia.recordatorio'), [...$this->datosRecordatorio(), ...$datos]);
    }
}
