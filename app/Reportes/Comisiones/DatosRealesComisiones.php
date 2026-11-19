<?php

namespace App\Reportes\Comisiones;

use App\Models\FuenteDatos;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Liquidaciones calculadas con las metas de COM_META, la fuente de facturas
 * y los colaboradores de USERS. Solo consulta.
 *
 * Todavía no hay liquidaciones guardadas: el historial solo tiene la
 * liquidación activa y su estado vive en la sesión (DemoComisiones). Los
 * correos de los colaboradores son de ejemplo.test.
 *
 * Los períodos van desde AlcanceComisiones::PRIMER_PERIODO hasta el mes en
 * curso: "2026-10" es un mes y "2026-T4" el cierre del cuarto trimestre.
 *
 * @phpstan-import-type Resumen from DatosEjemploComisiones
 * @phpstan-import-type Liquidacion from DatosEjemploComisiones
 * @phpstan-import-type Asesor from DatosEjemploComisiones
 * @phpstan-import-type Envio from DatosEjemploComisiones
 */
class DatosRealesComisiones implements DatosComisiones
{
    public const CORREO_GERENCIA = 'gerencia@ejemplo.test';

    /** @var array<string, Liquidacion> */
    private array $calculadas = [];

    public function __construct(
        private CalcularComisiones $calculo,
        private DesgloseComisiones $desglose,
    ) {}

    /**
     * El último mes cerrado o, si aún no hay ninguno, el primer período.
     */
    public function periodoInicial(): string
    {
        $mesAnterior = $this->hoy()->subMonthNoOverflow()->format('Y-m');

        return max($mesAnterior, AlcanceComisiones::PRIMER_PERIODO);
    }

    /** @return list<array{id: string, etiqueta: string}> */
    public function periodosDisponibles(): array
    {
        $meses = [];
        $trimestres = [];
        $mes = CarbonImmutable::createFromFormat('!Y-m', AlcanceComisiones::PRIMER_PERIODO);
        $ultimo = $this->hoy()->startOfMonth();

        while ($mes <= $ultimo) {
            $meses[] = ['id' => $mes->format('Y-m'), 'etiqueta' => $this->etiqueta($mes->format('Y-m'))];
            $trimestre = $mes->format('Y').'-T'.$mes->quarter;
            $trimestres[$trimestre] = ['id' => $trimestre, 'etiqueta' => $this->etiqueta($trimestre)];
            $mes = $mes->addMonth();
        }

        return [...$meses, ...array_values($trimestres)];
    }

    /** @return list<Resumen> */
    public function liquidaciones(string $activa): array
    {
        $liquidacion = $this->liquidacion($activa, $activa) ?? $this->liquidacion($this->periodoInicial(), $this->periodoInicial());

        return [array_intersect_key($liquidacion, array_flip([
            'id', 'periodo', 'tipo_periodo', 'desde', 'hasta', 'estado', 'total',
            'colaboradores_con_comision', 'colaboradores', 'actualizada_en', 'es_demo_activa',
        ]))];
    }

    /** @return Liquidacion|null */
    public function liquidacion(string $id, string $activa): ?array
    {
        $periodo = $this->periodo($id);

        if ($periodo === null) {
            return null;
        }

        $this->calculadas[$id] ??= $this->calcular($id, $periodo);

        return [...$this->calculadas[$id], 'es_demo_activa' => $id === $activa];
    }

    /**
     * @param  Asesor  $asesor
     * @return Envio
     */
    public function envioPendiente(array $asesor): array
    {
        return [
            'asesor_id' => $asesor['id'],
            'nombre' => $asesor['nombre'],
            'correo' => $asesor['correo'],
            'estado' => 'pendiente',
            'enviado_en' => null,
            'error' => null,
        ];
    }

    /** @return Liquidacion */
    private function calcular(string $id, PeriodoComision $periodo): array
    {
        $resultado = $this->calculo->calcular($periodo);
        $desglose = $this->desglose->armar($resultado);
        $fuente = FuenteDatos::porCodigo(FuenteFacturasOracle::CODIGO);
        $desde = $periodo->desde->toDateString();
        $hasta = $periodo->hasta->toDateString();

        return [
            'id' => $id,
            'periodo' => $this->etiqueta($id),
            'tipo_periodo' => $periodo->periodicidad === Periodicidad::Trimestral ? 'trimestral' : 'mensual',
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => 'borrador',
            'total' => $desglose['total'],
            'colaboradores_con_comision' => $desglose['colaboradores_con_comision'],
            'colaboradores' => $desglose['colaboradores'],
            'actualizada_en' => ($fuente?->fecha_actualizacion ?? $this->hoy())->toIso8601String(),
            'es_demo_activa' => false,
            'base' => [
                'fuente' => $fuente->nombre ?? 'Facturas Oracle NetSuite',
                'archivo' => $fuente->archivo ?? '—',
                'filas' => $resultado->filasBase,
                'desde' => $desde,
                'hasta' => $hasta,
                'servicios_sin_configurar' => count($resultado->serviciosSinLinea),
                'nombres_sin_configurar' => count($resultado->nombresSinConfigurar),
            ],
            'indicadores' => $desglose['indicadores'],
            'cargos' => $desglose['cargos'],
            'resumen_cargos' => $desglose['resumen_cargos'],
            'puntos_revision' => $desglose['puntos_revision'],
            'asesores' => $desglose['asesores'],
            'envios' => [],
            'historial' => [],
            'correo_gerencia' => self::CORREO_GERENCIA,
        ];
    }

    private function periodo(string $id): ?PeriodoComision
    {
        if (! in_array($id, array_column($this->periodosDisponibles(), 'id'), true)) {
            return null;
        }

        [$anio, $parte] = explode('-', $id);

        return str_starts_with($parte, 'T')
            ? PeriodoComision::trimestre((int) $anio, (int) substr($parte, 1))
            : PeriodoComision::mes((int) $anio, (int) $parte);
    }

    private function etiqueta(string $id): string
    {
        [$anio, $parte] = explode('-', $id);

        if (str_starts_with($parte, 'T')) {
            return 'Cierre Q'.substr($parte, 1)." {$anio} (trimestral)";
        }

        return Str::ucfirst(CarbonImmutable::createFromFormat('!Y-m', $id)->locale('es')->translatedFormat('F Y'));
    }

    private function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(config('reportes.zona_horaria'));
    }
}
