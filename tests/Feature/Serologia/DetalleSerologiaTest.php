<?php

namespace Tests\Feature\Serologia;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DetalleSerologiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararListadoSerologia();
    }

    public function test_exige_autenticacion_para_consultar_el_detalle(): void
    {
        $this->get(route('serologia'))->assertRedirect(route('login'));
    }

    public function test_el_listado_no_incluye_detalles_de_otra_empresa(): void
    {
        $this->crearContratoPrueba();

        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '002']));
        $this->consultarListado()
            ->assertOk()
            ->assertJsonPath('props.resultados.data', []);
    }

    public function test_separa_la_fpp_del_parto_y_usa_la_sede_de_la_toma_seleccionada(): void
    {
        $this->travelTo(new CarbonImmutable('2026-11-10', 'America/Lima'));
        $this->crearContratoPrueba();
        $this->crearSedePrueba(sede: '01');
        $this->crearSedePrueba(sede: '02');
        $this->autenticar();

        DB::table('ven_clientes')->update(['celular_contacto' => '999000111']);
        DB::table('ven_contratos')->update([
            'numero_prospecto' => '100',
            'fecha_nacimiento' => '2026-10-17',
            'confirm_nac' => 0,
        ]);
        DB::table('ven_prospectos')->insert([
            'no_cia' => '001', 'numero_prospecto' => '100', 'fecha_parto' => '2026-10-19',
        ]);
        DB::table('lab_colectas')->insert([
            'no_cia' => '001', 'numero_contrato' => 'PE-CR-2026-000001-1',
            'fecha_nacimiento' => '2026-10-17',
        ]);
        DB::table('ope_tomas_serologicas')->update([
            'laboratorio_toma' => '20330025213', 'sede_toma' => '01',
        ]);
        DB::table('ope_tomas_serologicas')->insert([
            'no_cia' => '001', 'numero_contrato_base' => 'PE-CR-2026-000001-0',
            'numero_toma' => 2, 'laboratorio_toma' => '20330025213', 'sede_toma' => '02',
        ]);

        $this->consultarListado()
            ->assertOk()
            ->assertJsonPath('props.resultados.data.0.detalle.sede_toma', 'Sede 01')
            ->assertJsonPath('props.resultados.data.1.detalle.sede_toma', 'Sede 02')
            ->assertJsonPath('props.resultados.data.1.detalle.celular', '999000111')
            ->assertJsonPath('props.resultados.data.1.detalle.fpp', '2026-10-19')
            ->assertJsonPath('props.resultados.data.1.detalle.parto_real', '2026-10-17')
            ->assertJsonPath('props.resultados.data.1.detalle.plazo.fecha_apertura', '2026-10-07')
            ->assertJsonPath('props.resultados.data.1.detalle.plazo.fecha_limite', '2026-11-16')
            ->assertJsonPath('props.resultados.data.1.detalle.plazo.situacion', 'por_vencer');
    }

    public function test_no_reutiliza_el_parto_confirmado_como_fpp(): void
    {
        $this->crearContratoPrueba(conToma: false);
        $this->autenticar();
        DB::table('ven_contratos')->update(['confirm_nac' => 1]);

        $this->consultarListado()
            ->assertOk()
            ->assertJsonPath('props.resultados.data.0.detalle.parto_real', '2026-11-10')
            ->assertJsonPath('props.resultados.data.0.detalle.fpp', null)
            ->assertJsonPath('props.resultados.data.0.detalle.sede_toma', null);
    }

    public function test_usa_la_fecha_estimada_del_contrato_si_no_hay_parto_ni_prospecto(): void
    {
        $this->travelTo(new CarbonImmutable('2026-10-30', 'America/Lima'));
        $this->crearContratoPrueba();
        $this->autenticar();

        $this->consultarListado()
            ->assertOk()
            ->assertJsonPath('props.resultados.data.0.detalle.parto_real', null)
            ->assertJsonPath('props.resultados.data.0.detalle.fpp', '2026-11-10')
            ->assertJsonPath('props.resultados.data.0.detalle.plazo.fecha_apertura', '2026-10-31')
            ->assertJsonPath('props.resultados.data.0.detalle.plazo.fecha_limite', '2026-12-10')
            ->assertJsonPath('props.resultados.data.0.detalle.plazo.situacion', 'aun_no_abre');
    }

    private function autenticar(): void
    {
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']));
    }
}
