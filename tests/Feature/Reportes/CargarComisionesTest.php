<?php

namespace Tests\Feature\Reportes;

use App\Models\PruebaComision;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CargarComisionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPruebaComisiones();
    }

    private function usuario(): User
    {
        return UserFactory::new()->make(['id' => 1, 'empresa' => '001']);
    }

    private function csv(string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ventas.csv', $contenido);
    }

    public function test_carga_un_csv_con_las_columnas_esperadas(): void
    {
        $contenido = "\xEF\xBB\xBFID interno;N° documento;Fecha de emisión;Importe\n"
            ."113688;F040-00000078;22/07/2025;1875\n"
            ."115953;B040-00000104;30/09/2025;-941.95\n"
            .";;;\n";

        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), ['archivo' => $this->csv($contenido)])
            ->assertRedirectToRoute('reportes.comisiones')
            ->assertSessionHasNoErrors();
    }

    public function test_acepta_un_csv_guardado_en_windows_1252(): void
    {
        $contenido = mb_convert_encoding(
            "ID interno,N° documento,Fecha de emisión,Importe\n113688,F040-00000078,22/07/2025,1875\n",
            'Windows-1252',
            'UTF-8',
        );

        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), ['archivo' => $this->csv($contenido)])
            ->assertRedirectToRoute('reportes.comisiones')
            ->assertSessionHasNoErrors();
    }

    public function test_rechaza_un_csv_al_que_le_faltan_columnas(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), ['archivo' => $this->csv("ID interno;Importe\n1;10\n")])
            ->assertSessionHasErrors(['archivo' => 'Faltan columnas en el archivo: N° documento, Fecha de emisión.']);
    }

    public function test_rechaza_un_archivo_que_no_es_csv(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), ['archivo' => UploadedFile::fake()->create('ventas.pdf', 10)])
            ->assertSessionHasErrors(['archivo' => 'El archivo debe ser .csv']);
    }

    public function test_guarda_las_filas_convertidas(): void
    {
        $contenido = "\xEF\xBB\xBFID interno;N° documento;Fecha de emisión;Importe\n"
            ."113688;F040-00000078;24/07/2025;1875\n"
            ."115953;B040-00000104;6/08/2025;-941.95\n";

        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), ['archivo' => $this->csv($contenido)])
            ->assertRedirectToRoute('reportes.comisiones')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('prueba_comisiones', 2);
        $this->assertDatabaseHas('prueba_comisiones', [
            'id_interno' => 115953,
            'numero_documento' => 'B040-00000104',
            'fecha_emision' => '2025-08-06 00:00:00',
        ]);
        $this->assertSame('-941.95', PruebaComision::find(115953)->importe);
    }

    public function test_reemplaza_los_datos_de_la_carga_anterior(): void
    {
        PruebaComision::query()->insert(['id_interno' => 1, 'numero_documento' => 'ANTERIOR']);

        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), [
                'archivo' => $this->csv("ID interno;N° documento;Fecha de emisión;Importe\n2;NUEVO;1/01/2026;10\n"),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('prueba_comisiones', ['id_interno' => 1]);
        $this->assertDatabaseHas('prueba_comisiones', ['id_interno' => 2, 'numero_documento' => 'NUEVO']);
    }

    public function test_una_fila_invalida_no_guarda_nada_y_conserva_la_carga_anterior(): void
    {
        PruebaComision::query()->insert(['id_interno' => 1, 'numero_documento' => 'ANTERIOR']);

        $contenido = "ID interno;N° documento;Fecha de emisión;Importe\n"
            ."2;F040-1;24/07/2025;10\n"
            ."3;F040-2;31/02/2025;1,875.00\n"
            ."2;F040-3;24/07/2025;10\n";

        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), ['archivo' => $this->csv($contenido)])
            ->assertSessionHasErrors(['archivo' => 'No se guardó ninguna fila. '
                .'Fila 3: la fecha «31/02/2025» no es válida; usa el formato día/mes/año. '
                .'Fila 4: el ID interno 2 ya aparece en la fila 2.']);

        $this->assertDatabaseCount('prueba_comisiones', 1);
        $this->assertDatabaseHas('prueba_comisiones', ['id_interno' => 1]);
    }

    public function test_rechaza_un_archivo_sin_filas(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('reportes.comisiones.cargar'), [
                'archivo' => $this->csv("ID interno;N° documento;Fecha de emisión;Importe\n"),
            ])
            ->assertSessionHasErrors(['archivo' => 'El archivo no tiene filas para cargar.']);
    }

    public function test_cargar_requiere_iniciar_sesion(): void
    {
        $this->post(route('reportes.comisiones.cargar'), ['archivo' => $this->csv("ID interno\n1\n")])
            ->assertRedirectToRoute('login');
    }
}
