<?php

namespace Tests\Unit\Reportes;

use App\Reportes\Comisiones\Atribucion;
use App\Reportes\Comisiones\BaseTramo;
use App\Reportes\Comisiones\MetaComision;
use App\Reportes\Comisiones\Periodicidad;
use App\Reportes\Comisiones\TipoMeta;
use App\Reportes\Comisiones\TramoMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los valores de las metas son los de COM_META de octubre a diciembre 2026.
 */
class MetaComisionTest extends TestCase
{
    /**
     * @param  list<array{float, ?float, float}>  $tramos  desde, hasta, valor
     */
    private function meta(TipoMeta $tipo, BaseTramo $base, float $minimo, array $tramos): MetaComision
    {
        return new MetaComision(
            id: 1,
            rol: 'EJECUTIVO DE VENTAS',
            atribucion: Atribucion::Representante,
            linea: 'UCU',
            periodicidad: Periodicidad::Mensual,
            tipo: $tipo,
            baseTramo: $base,
            minimo: $minimo,
            tramos: array_map(
                fn (array $tramo, int $indice): TramoMeta => new TramoMeta($indice + 1, ...$tramo),
                $tramos,
                array_keys($tramos),
            ),
        );
    }

    private function ucuEjecutivoMensual(): MetaComision
    {
        return $this->meta(TipoMeta::PorContrato, BaseTramo::Cantidad, 15, [
            [15, 16, 75], [17, 18, 100], [19, 20, 125], [21, null, 150],
        ]);
    }

    /**
     * @return array<string, array{int, ?int, float}>
     */
    public static function contratosUcu(): array
    {
        return [
            'debajo del mínimo' => [14, null, 0.0],
            'justo en el mínimo' => [15, 1, 1125.0],
            'fin del primer tramo' => [16, 1, 1200.0],
            'segundo tramo' => [17, 2, 1700.0],
            'último tramo sin tope' => [25, 4, 3750.0],
        ];
    }

    #[DataProvider('contratosUcu')]
    public function test_por_contrato_multiplica_los_contratos_por_el_valor_del_tramo(int $contratos, ?int $tramo, float $comision): void
    {
        $resultado = $this->ucuEjecutivoMensual()->evaluar($contratos, 40000);

        $this->assertSame($tramo, $resultado->tramo?->numero);
        $this->assertSame($comision, $resultado->comision);
        $this->assertSame($tramo !== null, $resultado->alcanzoMinimo());
    }

    public function test_informa_cuanto_falto_para_el_minimo(): void
    {
        $meta = $this->ucuEjecutivoMensual();

        $this->assertSame(3.0, $meta->evaluar(12, 0)->faltanteParaMinimo());
        $this->assertSame(0.0, $meta->evaluar(18, 0)->faltanteParaMinimo());
    }

    public function test_porcentaje_por_anios_de_atraso_se_aplica_sobre_el_monto(): void
    {
        $morosos = $this->meta(TipoMeta::Porcentaje, BaseTramo::Cantidad, 3, [
            [3, 3, 0.05], [4, 4, 0.10], [5, null, 0.15],
        ]);

        $this->assertSame(103.29, $morosos->evaluar(3, 2065.83)->comision);
        $this->assertSame(59.32, $morosos->evaluar(4, 593.20)->comision);
        $this->assertSame(150.0, $morosos->evaluar(7, 1000)->comision);
        $this->assertSame(0.0, $morosos->evaluar(2, 5000)->comision);
    }

    public function test_porcentaje_sobre_monto_usa_el_monto_para_ubicar_el_tramo(): void
    {
        $anualidades = $this->meta(TipoMeta::Porcentaje, BaseTramo::Monto, 550000, [
            [550000, null, 0.0025],
        ]);

        $this->assertSame(0.0, $anualidades->evaluar(1108, 459007.11)->comision);
        $this->assertSame(90992.89, $anualidades->evaluar(1108, 459007.11)->faltanteParaMinimo());
        $this->assertSame(1435.11, $anualidades->evaluar(1108, 574045.29)->comision);
    }

    public function test_monto_fijo_paga_el_valor_del_tramo(): void
    {
        $meta = $this->meta(TipoMeta::MontoFijo, BaseTramo::Cantidad, 10, [[10, null, 300]]);

        $this->assertSame(300.0, $meta->evaluar(40, 99999)->comision);
        $this->assertSame(0.0, $meta->evaluar(9, 99999)->comision);
    }
}
