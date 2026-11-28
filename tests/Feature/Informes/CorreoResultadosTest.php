<?php

namespace Tests\Feature\Informes;

use App\Mail\DocumentosContrato;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CorreoResultadosTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInformes();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'correo-resultados-'.uniqid();
        mkdir($this->raiz);
        config([
            'laboratorio.raices' => ['001' => $this->raiz, '002' => null],
            'laboratorio.registrar_envio' => false,
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->raiz);

        parent::tearDown();
    }

    public function test_si_ambos_son_aptos_envia_la_plantilla_de_aptos(): void
    {
        $this->crearContrato('BASE-1', sangre: 'APTO', tejido: 'APTO');
        Mail::fake();

        $this->enviar('BASE-1')->assertOk();

        Mail::assertSent(DocumentosContrato::class, function (DocumentosContrato $correo): bool {
            $correo->assertSeeInHtml('Estimada familia Pérez', false);
            $correo->assertSeeInHtml('contrato N° <strong>BASE-1</strong> ha concluido', false);
            $correo->assertSeeInHtml('Sangre de cordón umbilical: Apto para criopreservación', false);
            $correo->assertSeeInHtml('Tejido de cordón umbilical: Apto para criopreservación', false);
            $correo->assertDontSeeInHtml('NO APTO', false);

            return true;
        });
    }

    public function test_si_uno_no_es_apto_envia_la_plantilla_con_el_motivo_elegido(): void
    {
        $this->crearContrato('BASE-1', sangre: 'APTO', tejido: 'NO APTO');
        Mail::fake();

        $this->enviar('BASE-1', ['motivo_tejido' => 'cultivo_positivo'])->assertOk();

        Mail::assertSent(DocumentosContrato::class, function (DocumentosContrato $correo): bool {
            $correo->assertSeeInHtml('Los resultados obtenidos son los siguientes', false);
            $correo->assertSeeInHtml('Sangre de cordón umbilical: Apto para criopreservación', false);
            $correo->assertSeeInHtml('Tejido de cordón umbilical: No apto por cultivo positivo', false);
            $correo->assertSeeInHtml('no constituye un', false);
            $correo->assertSeeInText('Tejido de cordón umbilical: No apto por cultivo positivo');

            return true;
        });
    }

    public function test_exige_el_motivo_cuando_un_servicio_es_no_apto(): void
    {
        $this->crearContrato('BASE-1', sangre: 'NO APTO', tejido: 'APTO');
        Mail::fake();

        $this->enviar('BASE-1')->assertUnprocessable()->assertJsonValidationErrors('documentos');
        $this->enviar('BASE-1', ['motivo_tejido' => 'baja_viabilidad'])->assertUnprocessable();
        $this->enviar('BASE-1', ['motivo_sangre' => 'inventado'])->assertUnprocessable()->assertJsonValidationErrors('motivo_sangre');

        Mail::assertNothingSent();
    }

    public function test_no_envia_si_un_servicio_aun_no_esta_liberado(): void
    {
        $this->crearContrato('BASE-1', sangre: 'APTO', tejido: 'APTO', tejidoEnInfo: false);
        Mail::fake();

        $this->enviar('BASE-1')->assertUnprocessable()
            ->assertJsonValidationErrors(['documentos' => 'Un servicio del contrato aún no está liberado; no se puede enviar el informe.']);

        Mail::assertNothingSent();
    }

    public function test_la_vista_previa_devuelve_el_asunto_y_el_cuerpo_sin_enviar_nada(): void
    {
        $this->crearContrato('BASE-1', sangre: 'NO APTO', tejido: 'NO APTO');
        Mail::fake();

        $this->vistaPrevia('BASE-1', ['motivo_sangre' => 'baja_celularidad', 'motivo_tejido' => 'baja_viabilidad'])
            ->assertOk()
            ->assertJsonPath('asunto', 'Documentos de tu contrato N.º BASE-1')
            ->assertJson(fn ($json) => $json->where('html', fn (string $html): bool => str_contains($html, 'Sangre de cordón umbilical: No apto por baja celularidad')
                && str_contains($html, 'Tejido de cordón umbilical: No apto por baja viabilidad'))->etc());

        $this->vistaPrevia('BASE-1')->assertUnprocessable();
        $this->vistaPrevia('NO-EXISTE')->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_el_envio_masivo_omite_los_contratos_que_requieren_elegir_un_motivo(): void
    {
        $this->crearContrato('APTOS-1', sangre: 'APTO', tejido: 'APTO', identificacion: '11111111');
        $this->crearContrato('NOAPTO-1', sangre: 'APTO', tejido: 'NO APTO', identificacion: '22222222');
        Mail::fake();

        $respuesta = $this->actingAs($this->usuario())
            ->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')
            ->postJson(route('informes.documentos.enviar-masivo'), ['contratos' => [
                ['numero_base' => 'APTOS-1', 'identificacion' => '11111111'],
                ['numero_base' => 'NOAPTO-1', 'identificacion' => '22222222'],
            ]])->assertOk();

        $this->assertSame(['APTOS-1'], $respuesta->json('enviados'));
        $this->assertSame('Elige el motivo del resultado NO APTO desde «Ver documentos».', $respuesta->json('omitidos.0.motivo'));
        Mail::assertSent(DocumentosContrato::class, 1);
    }

    public function test_el_detalle_de_documentos_ofrece_los_motivos_de_no_apto(): void
    {
        $this->crearContrato('BASE-1', sangre: 'APTO', tejido: 'APTO');

        $this->actingAs($this->usuario())
            ->getJson(route('informes.documentos', ['numero_base' => 'BASE-1']))
            ->assertOk()
            ->assertJsonPath('motivos', [
                ['valor' => 'baja_viabilidad', 'etiqueta' => 'No apto por baja viabilidad'],
                ['valor' => 'cultivo_positivo', 'etiqueta' => 'No apto por cultivo positivo'],
                ['valor' => 'baja_celularidad', 'etiqueta' => 'No apto por baja celularidad'],
            ]);
    }

    private function crearContrato(string $base, string $sangre, string $tejido, string $identificacion = '11111111', bool $tejidoEnInfo = true): void
    {
        foreach (['S' => [$sangre, true], 'T' => [$tejido, $tejidoEnInfo]] as $servicio => [$liberacion, $enInfo]) {
            $this->crearContratoInformesPrueba(
                ['numero_contrato' => "{$base}-{$servicio}", 'numero_base' => $base, 'servicio' => $servicio, 'identificacion1' => $identificacion, 'liberacion' => $liberacion],
                ['nombre' => "Cliente {$base}", 'nombre_corto' => 'Cliente', 'apaterno' => 'Pérez'],
                true,
                $enInfo,
            );
        }

        $carpeta = $this->raiz.DIRECTORY_SEPARATOR.$base.'-CARPETA';
        mkdir($carpeta);
        file_put_contents($carpeta.DIRECTORY_SEPARATOR.'RHE-1.pdf', 'informe');
    }

    private function usuario(): object
    {
        return UserFactory::new()->make(['id' => 1, 'empresa' => '001', 'codigo' => 'AGIUSTI']);
    }

    /**
     * @param  array<string, string>  $motivos
     */
    private function enviar(string $base, array $motivos = []): TestResponse
    {
        return $this->actingAs($this->usuario())
            ->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')
            ->postJson(route('informes.documentos.enviar'), [
                'numero_base' => $base, 'identificacion' => '11111111', 'archivos' => ['RHE-1.pdf'], ...$motivos,
            ]);
    }

    /**
     * @param  array<string, string>  $motivos
     */
    private function vistaPrevia(string $base, array $motivos = []): TestResponse
    {
        return $this->actingAs($this->usuario())
            ->getJson(route('informes.documentos.vista-previa', ['numero_base' => $base, ...$motivos]));
    }
}
