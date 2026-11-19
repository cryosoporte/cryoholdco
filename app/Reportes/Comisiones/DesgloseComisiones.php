<?php

namespace App\Reportes\Comisiones;

use Illuminate\Support\Str;

/**
 * Convierte el resultado del cálculo en lo que muestran la pantalla y los
 * correos de Comisiones: pestañas por cargo, detalle por colaborador,
 * indicadores y puntos a revisar. Tiene la misma forma que los datos de
 * ejemplo.
 *
 * @phpstan-import-type Cargo from DatosEjemploComisiones
 * @phpstan-import-type Asesor from DatosEjemploComisiones
 * @phpstan-import-type Linea from DatosEjemploComisiones
 * @phpstan-import-type Celda from DatosEjemploComisiones
 * @phpstan-import-type Indicador from DatosEjemploComisiones
 * @phpstan-import-type ResumenCargo from DatosEjemploComisiones
 *
 * @phpstan-type Desglose array{total: float, colaboradores_con_comision: int, colaboradores: int, cargos: list<Cargo>, resumen_cargos: list<ResumenCargo>, asesores: list<Asesor>, indicadores: list<Indicador>, puntos_revision: list<string>}
 */
class DesgloseComisiones
{
    /** Las metas de estas líneas se resumen como indicadores de la liquidación. */
    private const LINEAS_INDICADOR = ['UCU', 'ANUALIDAD'];

    /** @return Desglose */
    public function armar(ResultadoComisiones $resultado): array
    {
        $cargos = [];
        $asesores = [];

        foreach (AlcanceComisiones::CARGOS as $rol => [$codigo, $etiqueta, $titulo]) {
            $comisiones = array_values(array_filter(
                $resultado->comisiones,
                fn (ComisionCalculada $comision): bool => $comision->asesor->rol === $rol,
            ));

            if ($comisiones === []) {
                continue;
            }

            [$cargo, $equipo] = $this->cargo($codigo, $etiqueta, $titulo, $comisiones);
            $cargos[] = $cargo;
            array_push($asesores, ...$equipo);
        }

        $conComision = count(array_filter($asesores, fn (array $asesor): bool => $asesor['total'] > 0));
        $total = round(array_sum(array_column($cargos, 'total')), 2);

        return [
            'total' => $total,
            'colaboradores_con_comision' => $conComision,
            'colaboradores' => count($asesores),
            'cargos' => $cargos,
            'resumen_cargos' => array_map(fn (array $cargo): array => [
                'cargo' => $cargo['etiqueta'],
                'con_comision' => count(array_filter($cargo['filas'], fn (array $fila): bool => $fila['comision'] > 0)),
                'colaboradores' => count($cargo['filas']),
                'total' => $cargo['total'],
            ], $cargos),
            'asesores' => $asesores,
            'indicadores' => $this->indicadores($resultado, $total, $conComision, count($asesores)),
            'puntos_revision' => $this->puntosRevision($resultado, $asesores),
        ];
    }

    /**
     * @param  list<ComisionCalculada>  $comisiones  del mismo rol
     * @return array{Cargo, list<Asesor>}
     */
    private function cargo(string $codigo, string $etiqueta, string $titulo, array $comisiones): array
    {
        $metas = [];
        $porAsesor = [];

        foreach ($comisiones as $comision) {
            $metas[$comision->resultado->meta->id] = $comision->resultado->meta;
            $porAsesor[$comision->asesor->id][] = $comision;
        }

        $filas = [];
        $asesores = [];

        foreach ($porAsesor as $suyas) {
            $asesor = $suyas[0]->asesor;
            $lineas = array_map($this->linea(...), $suyas);
            $totalAsesor = round(array_sum(array_column($lineas, 'comision')), 2);
            $nombre = Str::title(Str::lower(Str::squish($asesor->nombre)));

            $filas[] = [
                'asesor_id' => (string) $asesor->id,
                'nombre' => $nombre,
                'celdas' => array_map($this->celda(...), $suyas),
                'comision' => $totalAsesor,
            ];
            $asesores[] = [
                'id' => (string) $asesor->id,
                'nombre' => $nombre,
                'nombre_corto' => Str::before($nombre, ' '),
                'correo' => Str::slug($nombre, '.').'@ejemplo.test',
                'cargo_codigo' => $codigo,
                'cargo' => $etiqueta,
                'total' => $totalAsesor,
                'lineas' => $lineas,
                'nota' => $this->nota($suyas),
            ];
        }

        $metas = array_values($metas);

        return [[
            'codigo' => $codigo,
            'etiqueta' => $etiqueta,
            'titulo' => $titulo,
            'regla' => $this->regla($metas),
            'columnas' => array_map($this->columna(...), $metas),
            'filas' => $filas,
            'total' => round(array_sum(array_column($filas, 'comision')), 2),
        ], $asesores];
    }

    private function columna(MetaComision $meta): string
    {
        $linea = AlcanceComisiones::LINEAS[$meta->linea] ?? $meta->linea;

        if ($this->sinFuente($meta)) {
            return $linea;
        }

        $equipo = $meta->atribucion === Atribucion::Equipo ? ' equipo' : '';

        return "{$linea}{$equipo} ({$this->cantidadOMonto($meta, $meta->minimo)})";
    }

    /** @return Celda */
    private function celda(ComisionCalculada $comision): array
    {
        $resultado = $comision->resultado;
        $meta = $resultado->meta;

        if ($this->sinFuente($meta)) {
            return ['texto' => 'Sin fuente', 'alcanzo' => null];
        }

        $texto = $meta->baseTramo === BaseTramo::Monto
            ? $this->soles($resultado->monto)
            : $this->entero($resultado->cantidad).' / '.$this->entero($meta->minimo);

        return ['texto' => $texto, 'alcanzo' => $resultado->alcanzoMinimo()];
    }

    /** @return Linea */
    private function linea(ComisionCalculada $comision): array
    {
        $resultado = $comision->resultado;
        $meta = $resultado->meta;
        $concepto = (AlcanceComisiones::LINEAS[$meta->linea] ?? $meta->linea).match ($meta->atribucion) {
            Atribucion::Equipo => ' del equipo',
            Atribucion::Empresa => ' de la empresa',
            default => '',
        };

        if ($this->sinFuente($meta)) {
            return ['concepto' => $concepto, 'logrado' => '—', 'cuota' => '—', 'tramo' => 'Pendiente: falta la fuente de datos', 'comision' => 0.0];
        }

        $tramo = $resultado->tramo === null ? 'No alcanzó la cuota' : $this->describirTramo($meta, $resultado->tramo);
        $pagar = $comision->comision();

        if ($comision->adelanto !== null) {
            $pagar = $comision->pendiente();
            $tramo .= ' · trimestre '.$this->soles($comision->comision()).' − adelantado '.$this->soles($comision->adelanto);
        }

        return [
            'concepto' => $concepto,
            'logrado' => $meta->baseTramo === BaseTramo::Monto ? $this->soles($resultado->monto) : $this->entero($resultado->cantidad),
            'cuota' => $this->cantidadOMonto($meta, $meta->minimo),
            'tramo' => $tramo,
            'comision' => $pagar,
        ];
    }

    /**
     * @param  list<ComisionCalculada>  $comisiones  del mismo colaborador
     */
    private function nota(array $comisiones): string
    {
        $partes = [];

        foreach ($comisiones as $comision) {
            $meta = $comision->resultado->meta;

            if ($this->sinFuente($meta)) {
                $partes[] = 'La comisión por '.Str::lower(AlcanceComisiones::LINEAS[$meta->linea] ?? $meta->linea).' aún no está incluida: falta su fuente de datos.';

                break;
            }
        }

        foreach ($comisiones as $comision) {
            $meta = $comision->resultado->meta;

            if (! $this->sinFuente($meta) && ! $comision->resultado->alcanzoMinimo()) {
                $partes[] = $this->faltante($meta, $comision->resultado->faltanteParaMinimo());

                break;
            }
        }

        if ($comisiones[0]->adelanto !== null) {
            $partes[] = 'En el cierre trimestral se paga la comisión del trimestre menos lo adelantado en los meses.';
        }

        return $partes === [] ? 'Alcanzaste la cuota en todas tus líneas.' : implode(' ', $partes);
    }

    /**
     * "Te faltaron 2 contratos de ADN para la cuota de 5."
     */
    private function faltante(MetaComision $meta, float $faltante): string
    {
        $linea = AlcanceComisiones::LINEAS[$meta->linea] ?? $meta->linea;
        $cuota = $this->cantidadOMonto($meta, $meta->minimo);
        $sujeto = $meta->atribucion === Atribucion::Equipo ? 'Al equipo le' : 'Te';

        if ($meta->baseTramo === BaseTramo::Monto) {
            return "{$sujeto} faltaron {$this->soles($faltante, 0)} en {$linea} para la cuota de {$cuota}.";
        }

        $unidad = in_array($meta->linea, AlcanceComisiones::LINEAS_POR_FILA, true) ? 'línea' : 'contrato';
        $verbo = $faltante === 1.0 ? 'faltó' : 'faltaron';
        $unidades = $faltante === 1.0 ? $unidad : "{$unidad}s";

        return "{$sujeto} {$verbo} {$this->entero($faltante)} {$unidades} de {$linea} para la cuota de {$cuota}.";
    }

    /**
     * @param  list<MetaComision>  $metas
     */
    private function regla(array $metas): string
    {
        $atribucion = match ($metas[0]->atribucion) {
            Atribucion::Representante => 'Representante de ventas',
            Atribucion::Captador => 'Captador Final',
            Atribucion::Dueno => 'Dueño (cobrador)',
            Atribucion::Equipo => 'suma de su equipo',
            Atribucion::Empresa, Atribucion::Clinica => 'total de la línea',
        };
        $tramos = array_map(
            fn (MetaComision $meta): string => (AlcanceComisiones::LINEAS[$meta->linea] ?? $meta->linea).' '.implode(', ', array_map(
                fn (TramoMeta $tramo): string => $this->describirTramo($meta, $tramo),
                $meta->tramos,
            )),
            array_filter($metas, fn (MetaComision $meta): bool => ! $this->sinFuente($meta)),
        );

        return 'Atribución: '.$atribucion.' · '.implode(' · ', $tramos);
    }

    private function describirTramo(MetaComision $meta, TramoMeta $tramo): string
    {
        $desde = $this->cantidadOMonto($meta, $tramo->desde);
        $rango = match (true) {
            $tramo->hasta === null => "{$desde}+",
            $tramo->hasta === $tramo->desde => $desde,
            default => "{$desde}–{$this->cantidadOMonto($meta, $tramo->hasta)}",
        };

        return match ($meta->tipo) {
            TipoMeta::PorContrato => "{$rango} × {$this->soles($tramo->valor, 0)}",
            TipoMeta::Porcentaje => "{$rango}: ".rtrim(rtrim(number_format($tramo->valor * 100, 2), '0'), '.').'%',
            TipoMeta::MontoFijo => "{$rango}: {$this->soles($tramo->valor)}",
        };
    }

    /**
     * @return list<Indicador>
     */
    private function indicadores(ResultadoComisiones $resultado, float $total, int $conComision, int $colaboradores): array
    {
        $indicadores = [
            ['etiqueta' => 'Total a pagar', 'valor' => $this->soles($total), 'detalle' => null, 'tono' => 'neutral'],
            ['etiqueta' => 'Colaboradores con comisión', 'valor' => "{$conComision} de {$colaboradores}", 'detalle' => null, 'tono' => 'neutral'],
        ];

        foreach ($this->metasDeIndicador($resultado) as $comision) {
            $meta = $comision->resultado->meta;
            $linea = $this->linea($comision);
            [, $cargo] = AlcanceComisiones::CARGOS[$meta->rol] ?? [null, $meta->rol];

            $indicadores[] = [
                'etiqueta' => $linea['concepto'],
                'valor' => $linea['logrado'],
                'detalle' => "cuota {$linea['cuota']} ({$cargo})",
                'tono' => $comision->resultado->alcanzoMinimo() ? 'positivo' : 'atencion',
            ];
        }

        return $indicadores;
    }

    /**
     * @param  list<Asesor>  $asesores
     * @return list<string>
     */
    private function puntosRevision(ResultadoComisiones $resultado, array $asesores): array
    {
        $puntos = [];

        foreach ($resultado->nombresSinConfigurar as $nombre => $contratos) {
            $puntos[] = "«{$nombre}» figura en {$contratos} ".($contratos === 1 ? 'contrato' : 'contratos')
                .' y ningún usuario lo tiene como CODIGO_NETSUITE: no se le asigna comisión.';
        }

        foreach ($resultado->serviciosSinLinea as $servicio => $filas) {
            $puntos[] = "El servicio «{$servicio}» tiene {$filas} ".($filas === 1 ? 'fila' : 'filas')
                .' sin línea en COM_SERVICIO_LINEA: no cuenta para comisiones.';
        }

        $sinFuente = [];

        foreach ($resultado->comisiones as $comision) {
            if ($this->sinFuente($comision->resultado->meta)) {
                $sinFuente[$comision->resultado->meta->linea] = AlcanceComisiones::LINEAS[$comision->resultado->meta->linea] ?? $comision->resultado->meta->linea;
            }
        }

        foreach ($sinFuente as $linea) {
            $puntos[] = "{$linea}: aún no hay fuente de datos, esa comisión no está incluida en el total.";
        }

        foreach ($this->metasDeIndicador($resultado) as $comision) {
            if (! $comision->resultado->alcanzoMinimo()) {
                $linea = $this->linea($comision);
                $faltante = $this->cantidadOMonto($comision->resultado->meta, $comision->resultado->faltanteParaMinimo());
                $puntos[] = "{$linea['concepto']}: {$linea['logrado']}, faltaron {$faltante} para la cuota de {$linea['cuota']}.";
            }
        }

        $sinComision = count(array_filter($asesores, fn (array $asesor): bool => $asesor['total'] <= 0));

        if ($sinComision > 0) {
            $puntos[] = $sinComision === 1
                ? '1 colaborador no alcanzó la cuota mínima en ninguna línea.'
                : "{$sinComision} colaboradores no alcanzaron la cuota mínima en ninguna línea.";
        }

        return $puntos;
    }

    /**
     * Las metas de equipo o empresa de UCU y anualidades, una por rol y línea.
     *
     * @return list<ComisionCalculada>
     */
    private function metasDeIndicador(ResultadoComisiones $resultado): array
    {
        $elegidas = [];

        foreach ($resultado->comisiones as $comision) {
            $meta = $comision->resultado->meta;

            if (! $meta->atribucion->esPersonal() && in_array($meta->linea, self::LINEAS_INDICADOR, true)) {
                $elegidas[$meta->id] ??= $comision;
            }
        }

        return array_values($elegidas);
    }

    private function sinFuente(MetaComision $meta): bool
    {
        return in_array($meta->linea, AlcanceComisiones::LINEAS_SIN_FUENTE, true);
    }

    private function cantidadOMonto(MetaComision $meta, float $valor): string
    {
        return $meta->baseTramo === BaseTramo::Monto ? $this->soles($valor, 0) : $this->entero($valor);
    }

    private function entero(float $valor): string
    {
        return floor($valor) === $valor ? number_format($valor, 0) : number_format($valor, 2);
    }

    private function soles(float $valor, int $decimales = 2): string
    {
        return 'S/ '.number_format($valor, $decimales);
    }
}
