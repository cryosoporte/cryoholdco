<?php

namespace Tests\Feature\Serologia;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListadoSerologiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararListadoSerologia();
        $this->travelTo(new CarbonImmutable('2026-11-10 09:00', 'America/Lima'));
        $this->actingAs(UserFactory::new()->make(['id' => 1, 'empresa' => '001']));

        DB::table('users')->insert([
            ['empresa' => '001', 'identificacion' => '70000001', 'nombres' => 'Asesor Uno'],
            ['empresa' => '001', 'identificacion' => '70000002', 'nombres' => 'Asesor Dos'],
        ]);
    }

    public function test_cuenta_cada_estado_y_lista_los_asesores_de_la_empresa(): void
    {
        $this->crearClientesDeCadaEstado();

        $respuesta = $this->consultarListado()->assertOk()
            ->assertJsonPath('props.conteos', ['pendiente' => 2, 'tomada' => 1, 'recibido' => 1, 'por_vencer' => 1])
            ->assertJsonPath('props.asesores', [
                ['dni' => '70000002', 'nombre' => 'Asesor Dos'],
                ['dni' => '70000001', 'nombre' => 'Asesor Uno'],
            ])
            ->assertJsonPath('props.resultados.total', 4);

        $this->assertSame(['Ana Pendiente', 'Berta Por Vencer', 'Carla Tomada', 'Diana Recibido'], $this->nombres($respuesta));
    }

    /**
     * @param  list<string>  $esperados
     */
    #[DataProvider('filtrosDeEstado')]
    public function test_filtra_por_estado_sin_cambiar_los_conteos(string $estado, array $esperados): void
    {
        $this->crearClientesDeCadaEstado();

        $respuesta = $this->consultarListado(['estado' => $estado])->assertOk()
            ->assertJsonPath('props.estado', $estado)
            ->assertJsonPath('props.conteos', ['pendiente' => 2, 'tomada' => 1, 'recibido' => 1, 'por_vencer' => 1]);

        $this->assertSame($esperados, $this->nombres($respuesta));
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function filtrosDeEstado(): array
    {
        return [
            'pendiente' => ['pendiente', ['Ana Pendiente', 'Berta Por Vencer']],
            'tomada' => ['tomada', ['Carla Tomada']],
            'recibido' => ['recibido', ['Diana Recibido']],
            'por vencer' => ['por_vencer', ['Berta Por Vencer']],
        ];
    }

    #[DataProvider('busquedas')]
    public function test_busca_por_nombre_dni_o_contrato_sin_distinguir_mayusculas(string $buscar, string $esperado): void
    {
        $this->crearClientesDeCadaEstado();

        $respuesta = $this->consultarListado(['buscar' => "  {$buscar}  "])->assertOk()
            ->assertJsonPath('props.buscar', $buscar);

        $this->assertSame([$esperado], $this->nombres($respuesta));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function busquedas(): array
    {
        return [
            'nombre' => ['berta', 'Berta Por Vencer'],
            'dni' => ['33333333', 'Carla Tomada'],
            'contrato base' => ['pe-cr-2026-000004-0', 'Diana Recibido'],
        ];
    }

    public function test_la_busqueda_y_el_asesor_ajustan_los_conteos(): void
    {
        $this->crearClientesDeCadaEstado();

        $this->consultarListado(['buscar' => 'berta'])->assertOk()
            ->assertJsonPath('props.conteos', ['pendiente' => 1, 'tomada' => 0, 'recibido' => 0, 'por_vencer' => 1]);

        $respuesta = $this->consultarListado(['asesor' => '70000002'])->assertOk()
            ->assertJsonPath('props.asesor', '70000002')
            ->assertJsonPath('props.conteos', ['pendiente' => 0, 'tomada' => 1, 'recibido' => 1, 'por_vencer' => 0]);

        $this->assertSame(['Carla Tomada', 'Diana Recibido'], $this->nombres($respuesta));
    }

    public function test_pagina_de_20_en_20_y_conserva_los_filtros_en_los_enlaces(): void
    {
        foreach (range(1, 21) as $numero) {
            $this->crearCliente($numero, sprintf('Cliente %02d', $numero));
        }

        $this->consultarListado(['estado' => 'pendiente'])->assertOk()
            ->assertJsonCount(20, 'props.resultados.data')
            ->assertJsonPath('props.resultados.last_page', 2)
            ->assertJsonPath('props.resultados.next_page_url', fn (string $url): bool => str_contains($url, 'estado=pendiente'));

        $this->consultarListado(['estado' => 'pendiente', 'page' => 2])->assertOk()
            ->assertJsonPath('props.resultados.data.0.nombre', 'Cliente 21');
    }

    public function test_rechaza_un_estado_desconocido(): void
    {
        $this->from(route('serologia'))->get(route('serologia', ['estado' => 'otro']))
            ->assertRedirectToRoute('serologia')
            ->assertSessionHasErrors('estado');
    }

    /**
     * Crea un cliente por estado y tres contratos que el listado debe ignorar.
     */
    private function crearClientesDeCadaEstado(): void
    {
        $this->crearCliente(1, 'Ana Pendiente', fechaNacimiento: '2026-12-20', vendedor: '70000001');
        $this->crearCliente(2, 'Berta Por Vencer', fechaNacimiento: '2026-11-06', vendedor: '70000001');
        $this->crearCliente(3, 'Carla Tomada', fechaToma: '2026-11-05 10:00:00', vendedor: '70000002');
        $this->crearCliente(4, 'Diana Recibido', fechaToma: '2026-10-20 10:00:00', serologia: 'S', vendedor: '70000002');

        $this->crearCliente(5, 'Elena Otra Empresa', empresa: '002');
        $this->crearCliente(6, 'Fiorella Otro Servicio', servicio: 'X');
        $this->crearCliente(7, 'Gina Contrato Antiguo', fechaContrato: '2025-12-31');
    }

    private function crearCliente(
        int $numero,
        string $nombre,
        string $fechaNacimiento = '2026-12-20',
        ?string $fechaToma = null,
        ?string $serologia = null,
        ?string $vendedor = null,
        string $empresa = '001',
        string $servicio = 'S',
        string $fechaContrato = '2026-10-01',
    ): void {
        $dni = str_repeat((string) ($numero % 10), 8);
        $dni = $numero >= 10 ? sprintf('100000%02d', $numero) : $dni;
        $base = sprintf('PE-CR-2026-%06d-0', $numero);
        $contrato = sprintf('PE-CR-2026-%06d-1', $numero);

        DB::table('ven_clientes')->insert([
            'no_cia' => $empresa, 'identificacion' => $dni, 'nombre' => $nombre, 'nombre_corto' => $nombre,
        ]);
        DB::table('ven_contratos')->insert([
            'no_cia' => $empresa, 'numero_contrato' => $contrato, 'numero_base' => $base,
            'identificacion1' => $dni, 'codigo_documento1' => 'DI', 'servicio' => $servicio,
            'fecha_contrato' => $fechaContrato, 'fecha_nacimiento' => $fechaNacimiento, 'vendedor' => $vendedor,
        ]);
        DB::table('ope_tomas_serologicas')->insert([
            'no_cia' => $empresa, 'numero_contrato_base' => $base, 'numero_toma' => 1,
            'fecha_toma' => $fechaToma, 'fecha_resultado' => $serologia === null ? null : '2026-11-01 10:00:00',
        ]);

        if ($serologia !== null) {
            DB::table('lab_colectas')->insert([
                'no_cia' => $empresa, 'numero_contrato' => $contrato, 'serologia' => $serologia,
            ]);
        }
    }

    /** @return list<string> */
    private function nombres(TestResponse $respuesta): array
    {
        return array_column($respuesta->json('props.resultados.data'), 'nombre');
    }
}
