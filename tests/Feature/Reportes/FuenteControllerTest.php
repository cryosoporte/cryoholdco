<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Reportes\Fuentes\ColumnaFuente;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FuenteControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararFuentes();
    }

    private function usuario(): User
    {
        return UserFactory::new()->make(['id' => 1, 'empresa' => '001', 'name' => 'Luis Lazo']);
    }

    /**
     * Un .xlsx de Facturas Oracle subido como lo sube el navegador: PHP lo
     * guarda en un temporal sin extensión.
     *
     * @param  list<int>  $idsInternos
     */
    private function archivoFacturas(array $idsInternos, string $nombre = 'facturas.xlsx'): UploadedFile
    {
        $encabezados = array_map(fn (ColumnaFuente $columna): string => $columna->encabezado, (new FuenteFacturasOracle)->columnas());
        $filas = array_map(fn (int $id): array => [$id, ...array_fill(0, count($encabezados) - 1, null)], $idsInternos);

        return UploadedFile::fake()->createWithContent($nombre, file_get_contents($this->crearXlsx([$encabezados, ...$filas])));
    }

    public function test_lista_las_fuentes_con_su_ultima_actualizacion(): void
    {
        DB::table('fuentes_datos')->update([
            'fecha_actualizacion' => '2026-11-17 10:32:00',
            'actualizado_por' => 'Luis Lazo',
            'filas' => 13245,
            'archivo' => 'facturas.xlsx',
        ]);

        $this->actingAs($this->usuario())->get(route('reportes.fuentes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('reportes-fuentes')
                ->has('fuentes', 1)
                ->where('fuentes.0.codigo', 'FACTURAS_ORACLE')
                ->where('fuentes.0.nombre', 'Facturas Oracle NetSuite')
                ->where('fuentes.0.fecha_actualizacion', '2026-11-17T10:32:00-05:00')
                ->where('fuentes.0.actualizado_por', 'Luis Lazo')
                ->where('fuentes.0.filas', 13245)
                ->where('fuentes.0.archivo', 'facturas.xlsx'));
    }

    public function test_una_fuente_nunca_cargada_se_lista_sin_fecha(): void
    {
        $this->actingAs($this->usuario())->get(route('reportes.fuentes'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fuentes.0.fecha_actualizacion', null)
                ->where('fuentes.0.filas', null));
    }

    public function test_carga_un_xlsx_valido_y_registra_la_carga(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 15:32:00', 'UTC'));

        $this->actingAs($this->usuario())
            ->post(route('reportes.fuentes.cargar', 'facturas_oracle'), ['archivo' => $this->archivoFacturas([113688, 113688, 115953])])
            ->assertRedirectToRoute('reportes.fuentes')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('fuente_facturas_oracle', 3);
        $this->assertDatabaseHas('fuentes_datos', [
            'codigo' => 'FACTURAS_ORACLE',
            'fecha_actualizacion' => '2026-11-17 10:32:00',
            'actualizado_por' => 'Luis Lazo',
            'filas' => 3,
            'archivo' => 'facturas.xlsx',
        ]);
    }

    public function test_un_archivo_que_no_es_xlsx_se_rechaza_sin_tocar_la_fuente(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('reportes.fuentes.cargar', 'facturas_oracle'), ['archivo' => UploadedFile::fake()->create('facturas.xls', 10)])
            ->assertSessionHasErrors(['archivo' => 'El archivo debe ser .xlsx. Si NetSuite lo exportó como .xls, ábrelo y guárdalo como .xlsx.']);

        $this->assertDatabaseHas('fuentes_datos', ['codigo' => 'FACTURAS_ORACLE', 'fecha_actualizacion' => null]);
    }

    public function test_un_xlsx_con_errores_devuelve_el_mensaje_en_archivo(): void
    {
        $encabezados = ['ID interno', 'Cliente'];
        $archivo = UploadedFile::fake()->createWithContent('facturas.xlsx', file_get_contents($this->crearXlsx([$encabezados, [1, 'RIMAC']])));

        $this->actingAs($this->usuario())
            ->post(route('reportes.fuentes.cargar', 'facturas_oracle'), ['archivo' => $archivo])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('fuente_facturas_oracle', 0);
    }

    public function test_requiere_un_archivo(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('reportes.fuentes.cargar', 'facturas_oracle'))
            ->assertSessionHasErrors(['archivo' => 'Elige un archivo para cargar.']);
    }

    public function test_una_fuente_desconocida_responde_404(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('reportes.fuentes.cargar', 'no_existe'), ['archivo' => $this->archivoFacturas([1])])
            ->assertNotFound();
    }

    public function test_requiere_iniciar_sesion(): void
    {
        $this->get(route('reportes.fuentes'))->assertRedirectToRoute('login');
        $this->post(route('reportes.fuentes.cargar', 'facturas_oracle'), ['archivo' => $this->archivoFacturas([1])])
            ->assertRedirectToRoute('login');

        $this->assertDatabaseCount('fuente_facturas_oracle', 0);
    }
}
