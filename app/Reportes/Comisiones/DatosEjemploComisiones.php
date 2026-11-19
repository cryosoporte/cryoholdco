<?php

namespace App\Reportes\Comisiones;

use Illuminate\Support\Str;

/**
 * @phpstan-type CodigoCargo 'EJEC'|'LIMA'|'PROV'|'ANUAL'|'OPE'|'MKT'
 * @phpstan-type Estado 'borrador'|'enviada'|'rechazada'|'aprobada'|'enviada_personal'
 * @phpstan-type Resumen array{id: string, periodo: string, tipo_periodo: 'mensual'|'trimestral', desde: string, hasta: string, estado: Estado, total: float, colaboradores_con_comision: int, colaboradores: int, actualizada_en: string, es_demo_activa: bool}
 * @phpstan-type Base array{fuente: string, archivo: string, filas: int, desde: string, hasta: string, servicios_sin_configurar: int, nombres_sin_configurar: int}
 * @phpstan-type Indicador array{etiqueta: string, valor: string, detalle: ?string, tono: 'neutral'|'positivo'|'atencion'}
 * @phpstan-type Celda array{texto: string, alcanzo: ?bool}
 * @phpstan-type Fila array{asesor_id: string, nombre: string, celdas: list<Celda>, comision: float}
 * @phpstan-type Cargo array{codigo: CodigoCargo, etiqueta: string, titulo: string, regla: string, columnas: list<string>, filas: list<Fila>, total: float}
 * @phpstan-type ResumenCargo array{cargo: string, con_comision: int, colaboradores: int, total: float}
 * @phpstan-type Linea array{concepto: string, logrado: string, cuota: string, tramo: string, comision: float}
 * @phpstan-type Asesor array{id: string, nombre: string, nombre_corto: string, correo: string, cargo_codigo: CodigoCargo, cargo: string, total: float, lineas: list<Linea>, nota: string}
 * @phpstan-type Envio array{asesor_id: string, nombre: string, correo: string, estado: 'pendiente'|'enviado'|'fallido', enviado_en: ?string, error: ?string}
 * @phpstan-type Evento array{tipo: 'generada'|'procesada'|'rechazada'|'aprobada'|'enviada_personal'|'reenvio', fecha: string, por: string, comentario: ?string}
 * @phpstan-type Liquidacion array{id: string, periodo: string, tipo_periodo: 'mensual'|'trimestral', desde: string, hasta: string, estado: Estado, total: float, colaboradores_con_comision: int, colaboradores: int, actualizada_en: string, es_demo_activa: bool, base: Base, indicadores: list<Indicador>, cargos: list<Cargo>, resumen_cargos: list<ResumenCargo>, puntos_revision: list<string>, asesores: list<Asesor>, envios: list<Envio>, historial: list<Evento>, correo_gerencia: string}
 */
class DatosEjemploComisiones implements DatosComisiones
{
    public const PERIODO_INICIAL = '2026-10';

    private const TOTAL = 8187.98;

    private const CON_COMISION = 7;

    private const COLABORADORES = 13;

    private const PERIODOS = [
        '2026-08' => ['Agosto 2026', 'mensual', '2026-08-01', '2026-08-31', '2026-09-03T09:15:00-05:00'],
        '2026-09' => ['Septiembre 2026', 'mensual', '2026-09-01', '2026-09-30', '2026-10-03T09:15:00-05:00'],
        '2026-10' => ['Octubre 2026', 'mensual', '2026-10-01', '2026-10-31', '2026-11-03T09:15:00-05:00'],
        '2026-11' => ['Noviembre 2026', 'mensual', '2026-11-01', '2026-11-30', '2026-12-03T09:15:00-05:00'],
        '2026-12' => ['Diciembre 2026', 'mensual', '2026-12-01', '2026-12-31', '2027-01-04T09:15:00-05:00'],
        '2026-T4' => ['Cierre Q4 2026 (trimestral)', 'trimestral', '2026-10-01', '2026-12-31', '2027-01-04T09:15:00-05:00'],
    ];

    private const HISTORICAS = ['2026-09', '2026-08'];

    public function periodoInicial(): string
    {
        return self::PERIODO_INICIAL;
    }

    /** @return list<array{id: string, etiqueta: string}> */
    public function periodosDisponibles(): array
    {
        $periodos = [];

        foreach (self::PERIODOS as $id => [$etiqueta]) {
            if (! in_array($id, self::HISTORICAS, true)) {
                $periodos[] = ['id' => $id, 'etiqueta' => $etiqueta];
            }
        }

        return $periodos;
    }

    /** @return list<Resumen> */
    public function liquidaciones(string $activa = self::PERIODO_INICIAL): array
    {
        return [
            $this->resumen($activa, true),
            ...array_map(fn (string $id): array => $this->resumen($id, false), self::HISTORICAS),
        ];
    }

    /** @return Resumen */
    private function resumen(string $id, bool $activa): array
    {
        [$periodo, $tipo, $desde, $hasta, $actualizadaEn] = self::PERIODOS[$id];

        return [
            'id' => $id,
            'periodo' => $periodo,
            'tipo_periodo' => $tipo,
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => in_array($id, self::HISTORICAS, true) ? 'enviada_personal' : 'borrador',
            'total' => self::TOTAL,
            'colaboradores_con_comision' => self::CON_COMISION,
            'colaboradores' => self::COLABORADORES,
            'actualizada_en' => $actualizadaEn,
            'es_demo_activa' => $activa,
        ];
    }

    /** @return Liquidacion|null */
    public function liquidacion(string $id, string $activa = self::PERIODO_INICIAL): ?array
    {
        if (! isset(self::PERIODOS[$id])) {
            return null;
        }

        $resumen = $this->resumen($id, $id === $activa);
        $cargos = [];
        $asesores = [];
        $resumenCargos = [];

        foreach ($this->grupos() as ['cargo' => $cargo, 'asesores' => $equipo]) {
            $cargos[] = $cargo;
            array_push($asesores, ...$equipo);
            $resumenCargos[] = [
                'cargo' => $cargo['etiqueta'],
                'con_comision' => count(array_filter($cargo['filas'], fn (array $fila): bool => $fila['comision'] > 0)),
                'colaboradores' => count($cargo['filas']),
                'total' => $cargo['total'],
            ];
        }

        $cerrada = $resumen['estado'] === 'enviada_personal';

        return [
            ...$resumen,
            'base' => [
                'fuente' => 'Facturas Oracle NetSuite',
                'archivo' => 'facturas_'.Str::slug($resumen['periodo'], '_').'.xlsx',
                'filas' => 2211,
                'desde' => $resumen['desde'],
                'hasta' => $resumen['hasta'],
                'servicios_sin_configurar' => 0,
                'nombres_sin_configurar' => 0,
            ],
            'indicadores' => [
                ['etiqueta' => 'Total a pagar', 'valor' => 'S/ '.number_format($resumen['total'], 2), 'detalle' => null, 'tono' => 'neutral'],
                ['etiqueta' => 'Colaboradores con comisión', 'valor' => "$resumen[colaboradores_con_comision] de $resumen[colaboradores]", 'detalle' => null, 'tono' => 'neutral'],
                ['etiqueta' => 'Contratos UCU del equipo', 'valor' => '71', 'detalle' => 'de 75 (meta Jefe MKT)', 'tono' => 'atencion'],
                ['etiqueta' => 'Anualidades + vitalicios', 'valor' => 'S/ 574,045', 'detalle' => 'cuota superada', 'tono' => 'positivo'],
            ],
            'cargos' => $cargos,
            'resumen_cargos' => $resumenCargos,
            'puntos_revision' => [
                'El equipo de ventas cerró 71 contratos UCU; faltaron 4 para la meta de Jefe de Marketing (75).',
                'Anualidades + vitalicios: S/ 574,045.29, superó la cuota de S/ 550,000 (Jefe de Operaciones comisiona 0.25%).',
                '6 colaboradores no alcanzaron la cuota mínima en ninguna línea.',
            ],
            'asesores' => $asesores,
            'envios' => $cerrada ? array_map(
                fn (array $asesor): array => [...$this->envioPendiente($asesor), 'estado' => 'enviado', 'enviado_en' => $resumen['actualizada_en']],
                $asesores,
            ) : [],
            'historial' => $cerrada ? $this->historialDeEjemplo($resumen['actualizada_en']) : [],
            'correo_gerencia' => 'gerencia@ejemplo.test',
        ];
    }

    /** @return Asesor|null */
    public function asesor(string $liquidacionId, string $asesorId): ?array
    {
        foreach ($this->liquidacion($liquidacionId)['asesores'] ?? [] as $asesor) {
            if ($asesor['id'] === $asesorId) {
                return $asesor;
            }
        }

        return null;
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

    /** @return list<Evento> */
    private function historialDeEjemplo(string $fecha): array
    {
        return [
            ['tipo' => 'generada', 'fecha' => $fecha, 'por' => 'Analista de ejemplo', 'comentario' => null],
            ['tipo' => 'procesada', 'fecha' => $fecha, 'por' => 'Analista de ejemplo', 'comentario' => null],
            ['tipo' => 'aprobada', 'fecha' => $fecha, 'por' => 'Gerencia de ejemplo', 'comentario' => null],
            ['tipo' => 'enviada_personal', 'fecha' => $fecha, 'por' => 'Sistema', 'comentario' => null],
        ];
    }

    /** @return list<array{cargo: Cargo, asesores: list<Asesor>}> */
    private function grupos(): array
    {
        $columnasVentas = ['UCU (cuota 15)', 'Tamizaje (5)', 'ADN (5)', 'NIPT / GL (5)'];

        return [
            $this->grupo('EJEC', 'Ejecutivo de ventas', 'Ejecutivos de venta',
                'Atribución: Representante de ventas (paquete UCU) · tramos mensuales', $columnasVentas, 4925.00, [
                    $this->ventas('STECH', 'Stechmann Samame, Heinrrich', 'Heinrrich', 'heinrrich.stechmann', [16, 3, 3, 0], [1200.00, 0, 0, 0],
                        'Avance trimestral UCU: 16 de 45. Al cierre de diciembre se recalcula con la meta trimestral y, si corresponde, se paga la diferencia como recuperable.'),
                    $this->ventas('VBAZ', 'Vasquez Bazan, Carolina Andrea', 'Carolina', 'carolina.vasquez', [15, 7, 4, 3], [1125.00, 350.00, 0, 0],
                        'Avance trimestral UCU: 15 de 45. Al cierre de diciembre se recalcula con la meta trimestral.'),
                    $this->ventas('ROND', 'Rondon Fernandez, Liseth', 'Liseth', 'liseth.rondon', [15, 4, 1, 0], [1125.00, 0, 0, 0],
                        'Te faltó 1 tamizaje para llegar a la cuota de 5.'),
                    $this->ventas('ROSA', 'Rosales Cabrera, Adler Abad', 'Adler', 'adler.rosales', [15, 2, 2, 0], [1125.00, 0, 0, 0],
                        'Avance trimestral UCU: 15 de 45.'),
                    $this->ventas('CUBA', 'Cuba Michilot, Claudia Milagros', 'Claudia', 'claudia.cuba', [10, 1, 0, 1], [0, 0, 0, 0],
                        'Este mes no se alcanzó la cuota mínima. Tus contratos sí suman al cálculo trimestral (UCU: 10 de 45).'),
                ]),
            $this->grupo('LIMA', 'Rep. Prom. Lima', 'Representantes de promoción Lima',
                'Atribución: Captador Final · UCU 15+ × S/ 50, ADN y Tamizaje 5+ × S/ 30, NIPT 5+ × S/ 50', $columnasVentas, 0.00, [
                    $this->ventas('HERR', 'Herrera Ipanaque, Milagros', 'Milagros', 'milagros.herrera', [12, 1, 0, 1], [0, 0, 0, 0],
                        'Te faltaron 3 contratos UCU para la cuota de 15.'),
                    $this->ventas('CAST', 'Castillo Farro, Marylin', 'Marylin', 'marylin.castillo', [10, 3, 2, 0], [0, 0, 0, 0],
                        'Te faltaron 5 contratos UCU para la cuota de 15.'),
                ]),
            $this->grupo('PROV', 'Rep. Prom. Provincia', 'Representantes de promoción Provincia',
                'Atribución: Captador Final · UCU 10+ × S/ 50, ADN y Tamizaje 5+ × S/ 30, NIPT 5+ × S/ 50',
                ['UCU (cuota 10)', ...array_slice($columnasVentas, 1)], 0.00, [
                    $this->ventas('SARI', 'Sari Rodriguez, Diana Carolina', 'Diana', 'diana.sari', [1, 0, 0, 1], [0, 0, 0, 0],
                        'Este mes no se alcanzó la cuota mínima.', 10),
                    $this->ventas('YUGR', 'Yugra Azurin, Velia Fatima', 'Velia', 'velia.yugra', [1, 0, 0, 1], [0, 0, 0, 0],
                        'Este mes no se alcanzó la cuota mínima.', 10),
                ]),
            $this->grupo('ANUAL', 'Ejecutivo de anualidades', 'Ejecutivos de anualidades',
                'Atribución: Dueño (cobrador) · ST 5+ × S/ 50 · morosos 3 / 4 / 5 años: 5% / 10% / 15%',
                ['Seguridad Total (5)', 'Morosos 3 años', 'Morosos 4 años', 'Morosos 5 años'], 1827.87, [
                    ['id' => 'MARC', 'nombre' => 'Vasquez Monteza, Marcos Gabriel', 'nombre_corto' => 'Marcos', 'buzon' => 'marcos.vasquez',
                        'celdas' => [['33 / 5', true], ['S/ 2,065.83', null], ['S/ 593.20', null], ['—', null]],
                        'lineas' => [
                            ['Seguridad Total (líneas)', '33', '5', '5+ × S/ 50', 1650.00],
                            ['Morosos 3 años', 'S/ 2,065.83', '—', '5%', 103.29],
                            ['Morosos 4 años', 'S/ 593.20', '—', '10%', 59.32],
                        ],
                        'total' => 1812.61, 'nota' => 'Cartera morosa: contratos con 3 o más anualidades vencidas cobradas en el mes.'],
                    ['id' => 'KATH', 'nombre' => 'Cruzado Pineda, Katherine Diana', 'nombre_corto' => 'Katherine', 'buzon' => 'katherine.cruzado',
                        'celdas' => [['3 / 5', false], ['S/ 305.10', null], ['—', null], ['—', null]],
                        'lineas' => [
                            ['Seguridad Total (líneas)', '3', '5', '—', 0.00],
                            ['Morosos 3 años', 'S/ 305.10', '—', '5%', 15.26],
                        ],
                        'total' => 15.26, 'nota' => 'Te faltaron 2 líneas de Seguridad Total para la cuota de 5.'],
                ]),
            $this->grupo('OPE', 'Jefe de operaciones', 'Jefe de operaciones',
                'Base: subtotal S/ de anualidades + vitalicios del mes (incluye notas de crédito)',
                ['Base del mes', 'Cuota', 'Porcentaje', 'Estado'], 1435.11, [
                    ['id' => 'GIUS', 'nombre' => 'Giusti Diaz, Andrea', 'nombre_corto' => 'Andrea', 'buzon' => 'andrea.giusti',
                        'celdas' => [['S/ 574,045.29', true], ['S/ 550,000.00', null], ['0.25%', null], ['Cuota superada', true]],
                        'lineas' => [['Anualidades + vitalicios (S/ sin IGV)', 'S/ 574,045.29', 'S/ 550,000', '0.25% del total', 1435.11]],
                        'total' => 1435.11, 'nota' => 'La cuota mensual se superó en S/ 24,045.29.'],
                ]),
            $this->grupo('MKT', 'Jefe de marketing', 'Jefe de marketing',
                'Base: suma de los 5 ejecutivos de venta · 15 S/ por contrato al llegar a la cuota',
                ['UCU equipo (75)', 'Tamizaje (25)', 'ADN (25)', 'NIPT (25)'], 0.00, [
                    ['id' => 'WALC', 'nombre' => 'Walcheff Gonzalez, Daniel', 'nombre_corto' => 'Daniel', 'buzon' => 'daniel.walcheff',
                        'celdas' => [['71 / 75', false], ['17 / 25', false], ['10 / 25', false], ['4 / 25', false]],
                        'lineas' => [
                            ['UCU del equipo', '71', '75', '—', 0.00],
                            ['Tamizaje del equipo', '17', '25', '—', 0.00],
                            ['ADN+ del equipo', '10', '25', '—', 0.00],
                            ['NIPT del equipo', '4', '25', '—', 0.00],
                        ],
                        'total' => 0.00, 'nota' => 'Al equipo le faltaron 4 contratos UCU para la meta de 75.'],
                ]),
        ];
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<array{id: string, nombre: string, nombre_corto: string, buzon: string, celdas: list<array{string, ?bool}>, lineas: list<array{string, string, string, string, float}>, total: float, nota: string}>  $colaboradores
     * @return array{cargo: Cargo, asesores: list<Asesor>}
     */
    private function grupo(string $codigo, string $etiqueta, string $titulo, string $regla, array $columnas, float $total, array $colaboradores): array
    {
        $asesores = [];
        $filas = [];

        foreach ($colaboradores as $colaborador) {
            $asesores[] = [
                'id' => $colaborador['id'],
                'nombre' => $colaborador['nombre'],
                'nombre_corto' => $colaborador['nombre_corto'],
                'correo' => $colaborador['buzon'].'@ejemplo.test',
                'cargo_codigo' => $codigo,
                'cargo' => $etiqueta,
                'total' => $colaborador['total'],
                'lineas' => array_map(fn (array $linea): array => [
                    'concepto' => $linea[0],
                    'logrado' => $linea[1],
                    'cuota' => $linea[2],
                    'tramo' => $linea[4] > 0 ? $linea[3] : 'No alcanzó la cuota',
                    'comision' => $linea[4],
                ], $colaborador['lineas']),
                'nota' => $colaborador['nota'],
            ];
            $filas[] = [
                'asesor_id' => $colaborador['id'],
                'nombre' => $colaborador['nombre'],
                'celdas' => array_map(fn (array $celda): array => ['texto' => $celda[0], 'alcanzo' => $celda[1]], $colaborador['celdas']),
                'comision' => $colaborador['total'],
            ];
        }

        return ['cargo' => compact('codigo', 'etiqueta', 'titulo', 'regla', 'columnas', 'filas', 'total'), 'asesores' => $asesores];
    }

    /**
     * @param  array{int, int, int, int}  $logros
     * @param  array{float, float, float, float}  $comisiones
     * @return array{id: string, nombre: string, nombre_corto: string, buzon: string, celdas: list<array{string, ?bool}>, lineas: list<array{string, string, string, string, float}>, total: float, nota: string}
     */
    private function ventas(string $id, string $nombre, string $nombreCorto, string $buzon, array $logros, array $comisiones, string $nota, int $cuotaUcu = 15): array
    {
        $conceptos = [$id === 'STECH' ? 'UCU (contratos)' : 'UCU', 'Tamizaje', 'ADN+', 'NIPT / Genetic Loss'];
        $cuotas = [$cuotaUcu, 5, 5, 5];
        $tramos = ['15–16 × S/ 75', '5–9 × S/ 50', '—', '—'];
        $celdas = [];
        $lineas = [];

        foreach ($logros as $indice => $logrado) {
            $cuota = $cuotas[$indice];
            $celdas[] = ["$logrado / $cuota", $logrado >= $cuota];
            $lineas[] = [$conceptos[$indice], (string) $logrado, (string) $cuota, $tramos[$indice], (float) $comisiones[$indice]];
        }

        return [
            'id' => $id,
            'nombre' => $nombre,
            'nombre_corto' => $nombreCorto,
            'buzon' => $buzon,
            'celdas' => $celdas,
            'lineas' => $lineas,
            'total' => round(array_sum($comisiones), 2),
            'nota' => $nota,
        ];
    }
}
