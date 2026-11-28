<?php

namespace Tests\Feature\Reportes;

use App\Reportes\Comisiones\Atribucion;
use App\Reportes\Comisiones\BaseTramo;
use App\Reportes\Comisiones\MetaComision;
use App\Reportes\Comisiones\MetasComisiones;
use App\Reportes\Comisiones\Periodicidad;
use App\Reportes\Comisiones\PeriodoComision;
use App\Reportes\Comisiones\TipoMeta;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetasComisionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararComisiones();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{float, ?float, float}>  $tramos  desde, hasta, valor
     */
    private function crearMeta(int $id, array $meta, array $tramos = [[15, null, 75]]): void
    {
        DB::table('com_meta')->insert([
            'meta_id' => $id,
            'rol_cod' => 'EJECUTIVO DE VENTAS',
            'atribucion' => 'REPRESENTANTE',
            'linea_cod' => 'UCU',
            'periodicidad' => 'M',
            'tipo_cod' => 'POR_CONTRATO',
            'base_tramo' => 'CANTIDAD',
            'minimo' => 15,
            'vig_desde' => '2026-10-01 00:00:00',
            'vig_hasta' => '2026-12-31 00:00:00',
            'estado' => 'A',
            ...$meta,
        ]);

        foreach ($tramos as $indice => [$desde, $hasta, $valor]) {
            DB::table('com_meta_tramo')->insert([
                'meta_id' => $id,
                'nro_tramo' => $indice + 1,
                'desde' => $desde,
                'hasta' => $hasta,
                'valor' => $valor,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function idsVigentes(PeriodoComision $periodo): array
    {
        return array_map(fn (MetaComision $meta): int => $meta->id, (new MetasComisiones)->vigentes($periodo));
    }

    public function test_arma_cada_meta_con_sus_tramos_en_orden(): void
    {
        $this->crearMeta(1, [], []);
        DB::table('com_meta_tramo')->insert([
            ['meta_id' => 1, 'nro_tramo' => 3, 'desde' => 19, 'hasta' => 20, 'valor' => 125],
            ['meta_id' => 1, 'nro_tramo' => 1, 'desde' => 15, 'hasta' => 16, 'valor' => 75],
            ['meta_id' => 1, 'nro_tramo' => 4, 'desde' => 21, 'hasta' => null, 'valor' => 150],
            ['meta_id' => 1, 'nro_tramo' => 2, 'desde' => 17, 'hasta' => 18, 'valor' => 100],
        ]);
        $this->crearMeta(41, [
            'rol_cod' => 'EJECUTIVO DE ANUALIDADES',
            'atribucion' => 'DUENO',
            'linea_cod' => 'MOROSO',
            'tipo_cod' => 'PORCENTAJE',
            'minimo' => 3,
        ], [[3, 3, 0.05]]);

        $metas = (new MetasComisiones)->vigentes(PeriodoComision::mes(2026, 10));

        $this->assertCount(2, $metas);
        [$ucu, $morosos] = $metas;
        $this->assertSame('EJECUTIVO DE VENTAS', $ucu->rol);
        $this->assertSame(Atribucion::Representante, $ucu->atribucion);
        $this->assertSame(Periodicidad::Mensual, $ucu->periodicidad);
        $this->assertSame(TipoMeta::PorContrato, $ucu->tipo);
        $this->assertSame(BaseTramo::Cantidad, $ucu->baseTramo);
        $this->assertSame(15.0, $ucu->minimo);
        $this->assertSame([1, 2, 3, 4], array_map(fn ($tramo) => $tramo->numero, $ucu->tramos));
        $this->assertSame([16.0, 18.0, 20.0, null], array_map(fn ($tramo) => $tramo->hasta, $ucu->tramos));
        $this->assertSame(1700.0, $ucu->evaluar(17, 0)->comision);
        $this->assertSame(Atribucion::Dueno, $morosos->atribucion);
        $this->assertSame(0.05, $morosos->tramos[0]->valor);
    }

    public function test_solo_trae_metas_activas_de_la_periodicidad_del_periodo(): void
    {
        $this->crearMeta(1, []);
        $this->crearMeta(5, ['periodicidad' => 'T', 'minimo' => 45]);
        $this->crearMeta(51, ['rol_cod' => 'JEFE DE OPERACIONES', 'estado' => 'I']);

        $this->assertSame([1], $this->idsVigentes(PeriodoComision::mes(2026, 11)));
        $this->assertSame([5], $this->idsVigentes(PeriodoComision::trimestre(2026, 4)));
    }

    public function test_la_vigencia_debe_cubrir_todo_el_periodo(): void
    {
        $this->crearMeta(1, ['vig_desde' => '2026-10-01 00:00:00', 'vig_hasta' => '2026-12-31 00:00:00']);

        $this->assertSame([1], $this->idsVigentes(PeriodoComision::mes(2026, 12)));
        $this->assertSame([], $this->idsVigentes(PeriodoComision::mes(2026, 9)));
        $this->assertSame([], $this->idsVigentes(PeriodoComision::mes(2027, 1)));
    }

    public function test_una_meta_sin_fecha_de_fin_sigue_vigente(): void
    {
        $this->crearMeta(1, ['vig_hasta' => null]);

        $this->assertSame([1], $this->idsVigentes(PeriodoComision::mes(2030, 6)));
    }

    public function test_si_dos_metas_del_mismo_rol_y_linea_cubren_el_periodo_gana_la_mas_reciente(): void
    {
        $this->crearMeta(1, ['vig_desde' => '2026-01-01 00:00:00', 'vig_hasta' => null]);
        $this->crearMeta(60, ['vig_desde' => '2026-10-01 00:00:00', 'vig_hasta' => null], [[20, null, 90]]);

        $this->assertSame([1], $this->idsVigentes(PeriodoComision::mes(2026, 9)));
        $this->assertSame([60], $this->idsVigentes(PeriodoComision::mes(2026, 10)));
    }
}
