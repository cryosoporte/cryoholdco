<?php

namespace Tests\Unit\Serologia;

use App\Serologia\PlazoSerologia;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlazoSerologiaTest extends TestCase
{
    #[DataProvider('registrosConMuestraOResultado')]
    public function test_muestra_la_fecha_del_estado_sin_barra_ni_vencimiento(
        ?string $serologia,
        ?string $fechaToma,
        ?string $fechaResultado,
        string $situacion,
        string $texto
    ): void {
        $calculador = new PlazoSerologia;

        $plazo = $calculador->paraRegistro(
            $serologia, null, $fechaToma, $fechaResultado,
            new CarbonImmutable('2026-11-07', 'America/Lima'),
        );

        $this->assertSame([
            'situacion' => $situacion,
            'fecha_apertura' => null,
            'fecha_limite' => null,
            'dias_restantes' => null,
            'porcentaje_transcurrido' => null,
            'texto' => $texto,
        ], $plazo);
    }

    /** @return array<string, array{?string, ?string, ?string, string, string}> */
    public static function registrosConMuestraOResultado(): array
    {
        return [
            'tomada con fecha' => [null, '2026-11-06', null, 'tomada', 'Tomada el 6 nov. 2026'],
            'resultado sin indicador S no cambia tomada' => [null, '2026-11-06', '2026-11-07', 'tomada', 'Tomada el 6 nov. 2026'],
            'S tiene prioridad sobre toma' => ['S', '2026-11-06', '2026-11-07', 'recibido', 'Resultado recibido el 7 nov. 2026'],
            'recibido sin toma' => ['S', null, '2026-11-07', 'recibido', 'Resultado recibido el 7 nov. 2026'],
            'recibido sin fecha de resultado no usa fecha de toma' => ['S', '2026-11-06', null, 'recibido', 'Fecha de resultado no registrada'],
            'recibido sin ninguna fecha' => ['S', null, null, 'recibido', 'Fecha de resultado no registrada'],
        ];
    }

    public function test_conserva_el_plazo_pendiente_si_solo_existe_fecha_de_resultado(): void
    {
        $plazo = (new PlazoSerologia)->paraRegistro(
            null, '2026-11-06', null, '2026-11-07',
            new CarbonImmutable('2026-11-07', 'America/Lima'),
        );

        $this->assertSame([
            'situacion' => 'por_vencer',
            'fecha_apertura' => '2026-10-07',
            'fecha_limite' => '2026-11-13',
            'dias_restantes' => 6,
            'porcentaje_transcurrido' => 84,
            'texto' => 'Quedan 6 días · 13 nov. 2026',
        ], $plazo);
    }

    #[DataProvider('fechasDelPlazo')]
    public function test_clasifica_el_plazo_por_dias_calendario(
        string $hoy,
        string $situacion,
        int $diasRestantes,
        int $porcentaje
    ): void {
        $calculador = new PlazoSerologia;

        $plazo = $calculador->calcular('2026-11-06', new CarbonImmutable($hoy, 'America/Lima'));

        $this->assertSame([
            'situacion' => $situacion,
            'fecha_apertura' => '2026-10-07',
            'fecha_limite' => '2026-11-13',
            'dias_restantes' => $diasRestantes,
            'porcentaje_transcurrido' => $porcentaje,
        ], array_diff_key($plazo, ['texto' => true]));
    }

    /** @return array<string, array{string, string, int, int}> */
    public static function fechasDelPlazo(): array
    {
        return [
            'mucho antes de abrir' => ['2026-09-01', 'aun_no_abre', 73, 0],
            'dia anterior a apertura' => ['2026-10-06', 'aun_no_abre', 38, 0],
            'dia de apertura incluido' => ['2026-10-07', 'en_plazo', 37, 0],
            'dia siguiente a apertura' => ['2026-10-08', 'en_plazo', 36, 3],
            'ocho dias restantes' => ['2026-11-05', 'en_plazo', 8, 78],
            'siete dias restantes' => ['2026-11-06', 'por_vencer', 7, 81],
            'un dia restante' => ['2026-11-12', 'por_vencer', 1, 97],
            'ultimo dia hasta medianoche' => ['2026-11-13 23:59:59', 'por_vencer', 0, 100],
            'dia posterior a vencimiento' => ['2026-11-14', 'fuera_de_plazo', -1, 100],
            'mucho despues de vencer' => ['2026-11-20', 'fuera_de_plazo', -7, 100],
        ];
    }

    public function test_informa_que_no_hay_fecha_sin_inventar_un_vencimiento(): void
    {
        $plazo = (new PlazoSerologia)->calcular(null, new CarbonImmutable('2026-11-06', 'America/Lima'));

        $this->assertSame([
            'situacion' => 'sin_fecha',
            'fecha_apertura' => null,
            'fecha_limite' => null,
            'dias_restantes' => null,
            'porcentaje_transcurrido' => null,
            'texto' => 'Sin fecha de nacimiento',
        ], $plazo);
    }

    public function test_usa_el_dia_de_lima_aunque_el_instante_este_en_utc(): void
    {
        $plazo = (new PlazoSerologia)->calcular('2026-11-06', new CarbonImmutable('2026-11-14 04:59:59', 'UTC'));

        $this->assertSame('por_vencer', $plazo['situacion']);
        $this->assertSame(0, $plazo['dias_restantes']);
    }

    public function test_calcula_la_apertura_cuando_cruza_el_ano(): void
    {
        $plazo = (new PlazoSerologia)->calcular('2027-01-10', new CarbonImmutable('2026-12-10', 'America/Lima'));

        $this->assertSame('2026-12-11', $plazo['fecha_apertura']);
        $this->assertSame('2027-01-17', $plazo['fecha_limite']);
        $this->assertSame('aun_no_abre', $plazo['situacion']);
    }

    public function test_cuenta_el_dia_bisiesto_en_la_ventana(): void
    {
        $plazo = (new PlazoSerologia)->calcular('2028-02-29', new CarbonImmutable('2028-03-01', 'America/Lima'));

        $this->assertSame('2028-01-30', $plazo['fecha_apertura']);
        $this->assertSame('2028-03-07', $plazo['fecha_limite']);
        $this->assertSame(6, $plazo['dias_restantes']);
    }

    public function test_el_texto_distingue_apertura_vencimiento_y_ultimo_dia(): void
    {
        $calculador = new PlazoSerologia;

        $antes = $calculador->calcular('2026-11-06', new CarbonImmutable('2026-10-06', 'America/Lima'));
        $ultimo = $calculador->calcular('2026-11-06', new CarbonImmutable('2026-11-13', 'America/Lima'));
        $despues = $calculador->calcular('2026-11-06', new CarbonImmutable('2026-11-14', 'America/Lima'));

        $this->assertSame('Abre el 7 oct. 2026', $antes['texto']);
        $this->assertSame('Vence hoy · 13 nov. 2026', $ultimo['texto']);
        $this->assertSame('Venció el 13 nov. 2026', $despues['texto']);
    }
}
