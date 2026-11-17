<?php

namespace Tests\Feature\Reportes;

use App\Reportes\Fuentes\FuenteFacturasOracle;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComisionesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararFuentes();
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']));
    }

    public function test_muestra_la_ultima_actualizacion_de_facturas_oracle(): void
    {
        DB::table('fuentes_datos')->where('codigo', FuenteFacturasOracle::CODIGO)->update([
            'fecha_actualizacion' => '2026-11-17 10:32:00',
            'actualizado_por' => 'Luis Lazo',
            'filas' => 29,
            'archivo' => 'facturas.xlsx',
        ]);

        $this->get(route('reportes.comisiones'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('comisiones')
                ->where('fuente', [
                    'codigo' => FuenteFacturasOracle::CODIGO,
                    'nombre' => 'Facturas Oracle NetSuite',
                    'fecha_actualizacion' => '2026-11-17T10:32:00-05:00',
                    'actualizado_por' => 'Luis Lazo',
                ]));
    }

    public function test_muestra_la_fuente_aunque_nunca_se_haya_cargado(): void
    {
        $this->get(route('reportes.comisiones'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fuente.codigo', FuenteFacturasOracle::CODIGO)
                ->where('fuente.fecha_actualizacion', null)
                ->where('fuente.actualizado_por', null));
    }

    public function test_muestra_comisiones_cuando_la_fuente_no_esta_registrada(): void
    {
        DB::table('fuentes_datos')->where('codigo', FuenteFacturasOracle::CODIGO)->delete();

        $this->get(route('reportes.comisiones'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('fuente', null));
    }
}
