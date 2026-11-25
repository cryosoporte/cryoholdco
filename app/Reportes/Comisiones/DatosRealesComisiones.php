<?php

namespace App\Reportes\Comisiones;

use App\Models\FuenteDatos;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Datos de la liquidación mensual o del cierre calculado con los meses aprobados.
 *
 * @phpstan-type CodigoCargo 'EJEC'|'LIMA'|'PROV'|'ANUAL'|'OPE'|'MKT'
 * @phpstan-type Estado 'borrador'|'enviada'|'rechazada'|'aprobada'|'enviada_personal'
 * @phpstan-type Base array{fuente: string, actualizacion_fuente?: array{codigo: string, nombre: string, fecha_actualizacion: ?string, actualizado_por: ?string}|null, archivo: string, filas: int, desde: string, hasta: string, servicios_sin_configurar: int, nombres_sin_configurar: int, liquidaciones_mensuales?: array<string, int>}
 * @phpstan-type Indicador array{etiqueta: string, valor: string, detalle: ?string, tono: 'neutral'|'positivo'|'atencion'}
 * @phpstan-type Celda array{texto: string, alcanzo: ?bool}
 * @phpstan-type Fila array{asesor_id: string, nombre: string, celdas: list<Celda>, comision: float}
 * @phpstan-type Cargo array{codigo: CodigoCargo, etiqueta: string, titulo: string, regla: string, columnas: list<string>, filas: list<Fila>, total: float}
 * @phpstan-type ResumenCargo array{cargo: string, con_comision: int, colaboradores: int, total: float}
 * @phpstan-type Linea array{concepto: string, logrado: string, cuota: string, tramo: string, comision: float}
 * @phpstan-type Asesor array{id: string, nombre: string, nombre_corto: string, correo: string, cargo_codigo: CodigoCargo, cargo: string, total: float, lineas: list<Linea>, nota: string}
 * @phpstan-type Envio array{asesor_id: string, nombre: string, correo: string, estado: 'pendiente'|'enviado'|'fallido', enviado_en: ?string, error: ?string}
 * @phpstan-type Evento array{tipo: 'procesada'|'rechazada'|'aprobada'|'enviada_personal'|'reenvio', fecha: string, por: string, comentario: ?string}
 * @phpstan-type Liquidacion array{id: string, periodo: string, tipo_periodo: 'mensual'|'trimestral', desde: string, hasta: string, estado: Estado, total: float, colaboradores_con_comision: int, colaboradores: int, actualizada_en: string, base: Base, indicadores: list<Indicador>, cargos: list<Cargo>, resumen_cargos: list<ResumenCargo>, puntos_revision: list<string>, asesores: list<Asesor>, envios: list<Envio>, historial: list<Evento>, correo_gerencia: string}
 */
class DatosRealesComisiones
{
    public const CORREO_GERENCIA = 'gerencia@ejemplo.test';

    public function __construct(private DesgloseComisiones $desglose) {}

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

    /**
     * Años que tienen al menos un período, del más reciente al más antiguo.
     *
     * @return list<int>
     */
    public function aniosDisponibles(): array
    {
        $primero = (int) substr(AlcanceComisiones::PRIMER_PERIODO, 0, 4);

        return range($this->anioActual(), $primero);
    }

    public function anioActual(): int
    {
        return $this->hoy()->year;
    }

    /** @return Liquidacion */
    public function desdeResultado(string $id, ResultadoComisiones $resultado): array
    {
        $periodo = $resultado->periodo;
        $desglose = $this->desglose->armar($resultado);
        $mensuales = $resultado->liquidacionesMensuales;
        $fuente = $mensuales === [] ? FuenteDatos::porCodigo(FuenteFacturasOracle::CODIGO) : null;
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
            'base' => [
                'fuente' => $mensuales === [] ? ($fuente->nombre ?? 'Facturas Oracle NetSuite') : 'Liquidaciones mensuales aprobadas',
                'actualizacion_fuente' => $fuente ? Arr::only($fuente->datosActualizacion(), ['codigo', 'nombre', 'fecha_actualizacion', 'actualizado_por']) : null,
                'archivo' => $mensuales === [] ? ($fuente->archivo ?? '—') : implode(', ', array_keys($mensuales)),
                ...($mensuales === [] ? [] : ['liquidaciones_mensuales' => $mensuales]),
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

    public function periodo(string $id): ?PeriodoComision
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
