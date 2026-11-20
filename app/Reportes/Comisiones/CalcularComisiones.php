<?php

namespace App\Reportes\Comisiones;

use Carbon\CarbonImmutable;

/**
 * Calcula las comisiones de un período con las metas vigentes, la base de
 * facturas y los colaboradores de USERS. Solo consulta: no guarda nada.
 *
 * Cada meta se evalúa para cada colaborador cuyo ROL_NETSUITE es el rol de la
 * meta, aunque no haya logrado nada (queda con 0). Lo logrado depende de la
 * atribución:
 * - personal (representante, captador, dueño): los contratos de la línea en
 *   los que la columna de la fuente coincide con su CODIGO_NETSUITE;
 * - equipo: la suma de lo logrado en la misma línea por los colaboradores del
 *   rol de su equipo (AlcanceComisiones::EQUIPOS);
 * - empresa o clínica: todos los contratos de la línea; en las líneas de
 *   AlcanceComisiones::LINEAS_CON_MONTO_NETO el monto es el neto de todos
 *   los comprobantes, notas de crédito incluidas.
 *
 * En un trimestre, cada comisión trimestral lleva como adelanto lo que el
 * colaborador cobró por la misma línea en los tres meses.
 */
class CalcularComisiones
{
    public function __construct(
        private MetasComisiones $metas,
        private BaseFacturasComisiones $base,
        private AsesoresComisiones $asesores,
    ) {}

    public function calcular(PeriodoComision $periodo): ResultadoComisiones
    {
        $asesores = $this->asesores->todos();
        $contratos = $this->base->contratos($periodo);
        $metas = $this->metas->vigentes($periodo);
        $morosas = $this->anualidadesMorosas($metas, $periodo);
        $comisiones = $this->evaluarMetas($metas, $contratos, $this->montosNetos($periodo), $morosas, $asesores);

        if ($periodo->periodicidad === Periodicidad::Trimestral) {
            $adelantos = $this->adelantos($periodo, $asesores);
            $comisiones = array_map(fn (ComisionCalculada $comision): ComisionCalculada => new ComisionCalculada(
                $comision->asesor,
                $comision->resultado,
                $adelantos[$comision->asesor->id.'|'.$comision->resultado->meta->linea] ?? 0.0,
                $comision->partes,
            ), $comisiones);
        }

        return new ResultadoComisiones(
            periodo: $periodo,
            comisiones: $comisiones,
            nombresSinConfigurar: $this->nombresSinConfigurar($metas, $contratos, $morosas, $asesores),
            serviciosSinLinea: $this->base->serviciosSinLinea($periodo),
            filasBase: array_sum(array_map(fn (ContratoComisionable $contrato): int => $contrato->filas, $contratos)),
            anualidadesSinPeriodo: $this->tieneLineaPorAntiguedad($metas) ? $this->base->anualidadesSinPeriodo($periodo) : 0,
        );
    }

    /**
     * @param  list<MetaComision>  $metas
     * @param  list<ContratoComisionable>  $contratos
     * @param  array<string, float>  $montosNetos
     * @param  list<AnualidadMorosa>  $morosas
     * @param  list<AsesorComision>  $asesores
     * @return list<ComisionCalculada>
     */
    private function evaluarMetas(array $metas, array $contratos, array $montosNetos, array $morosas, array $asesores): array
    {
        $comisiones = [];

        foreach ($metas as $meta) {
            foreach ($asesores as $asesor) {
                if ($asesor->rol !== $meta->rol) {
                    continue;
                }

                if (in_array($meta->linea, AlcanceComisiones::LINEAS_POR_ANTIGUEDAD, true)) {
                    $comisiones[] = $this->evaluarPorAntiguedad($meta, $asesor, $morosas);

                    continue;
                }

                [$cantidad, $monto] = $this->logrado($meta, $asesor, $metas, $contratos, $montosNetos, $asesores);
                $comisiones[] = new ComisionCalculada($asesor, $meta->evaluar($cantidad, $monto));
            }
        }

        return $comisiones;
    }

    /**
     * Agrupa las anualidades morosas del colaborador (por Dueño) en el tramo
     * de sus años y paga el porcentaje de cada tramo sobre su monto. El
     * resultado general lleva la cantidad de anualidades, el monto total y la
     * suma de las comisiones de los bloques.
     *
     * @param  list<AnualidadMorosa>  $morosas
     */
    private function evaluarPorAntiguedad(MetaComision $meta, AsesorComision $asesor, array $morosas): ComisionCalculada
    {
        $bloques = [];
        $cantidad = 0;
        $monto = 0.0;

        foreach ($morosas as $morosa) {
            if ($meta->atribucion !== Atribucion::Dueno || ! $this->mismoNombre($morosa->dueno, $asesor)) {
                continue;
            }

            $cantidad++;
            $monto += $morosa->monto;
            $tramo = $meta->tramoPara($morosa->anios);

            if ($tramo !== null) {
                $bloques[$tramo->numero] ??= [$tramo, 0.0];
                $bloques[$tramo->numero][1] += $morosa->monto;
            }
        }

        ksort($bloques);
        $partes = array_values(array_map(
            fn (array $bloque): ResultadoMeta => $meta->evaluar($bloque[0]->desde, round($bloque[1], 2)),
            $bloques,
        ));
        $comision = round(array_sum(array_map(fn (ResultadoMeta $parte): float => $parte->comision, $partes)), 2);

        return new ComisionCalculada(
            $asesor,
            new ResultadoMeta($meta, (float) $cantidad, round($monto, 2), null, $comision),
            partes: $partes,
        );
    }

    /**
     * @param  list<MetaComision>  $metas
     * @return list<AnualidadMorosa>
     */
    private function anualidadesMorosas(array $metas, PeriodoComision $periodo): array
    {
        if (! $this->tieneLineaPorAntiguedad($metas)) {
            return [];
        }

        return $this->base->anualidadesMorosas($periodo, CarbonImmutable::now(config('reportes.zona_horaria'))->year);
    }

    /**
     * @param  list<MetaComision>  $metas
     */
    private function tieneLineaPorAntiguedad(array $metas): bool
    {
        foreach ($metas as $meta) {
            if (in_array($meta->linea, AlcanceComisiones::LINEAS_POR_ANTIGUEDAD, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<MetaComision>  $metas
     * @param  list<ContratoComisionable>  $contratos
     * @param  array<string, float>  $montosNetos
     * @param  list<AsesorComision>  $asesores
     * @return array{float, float} cantidad y monto
     */
    private function logrado(MetaComision $meta, AsesorComision $asesor, array $metas, array $contratos, array $montosNetos, array $asesores): array
    {
        if ($meta->atribucion->esPersonal()) {
            return $this->sumar($meta->linea, array_filter(
                $contratos,
                fn (ContratoComisionable $contrato): bool => $this->esDe($contrato, $meta->atribucion, $asesor),
            ));
        }

        if ($meta->atribucion === Atribucion::Equipo) {
            return $this->logradoPorEquipo($meta, $metas, $contratos, $asesores);
        }

        [$cantidad, $monto] = $this->sumar($meta->linea, $contratos);

        if (in_array($meta->linea, AlcanceComisiones::LINEAS_CON_MONTO_NETO, true)) {
            $monto = $montosNetos[$meta->linea] ?? 0.0;
        }

        return [$cantidad, $monto];
    }

    /**
     * @return array<string, float> línea => monto neto
     */
    private function montosNetos(PeriodoComision $periodo): array
    {
        return $this->base->montosNetos($periodo, AlcanceComisiones::LINEAS_CON_MONTO_NETO);
    }

    /**
     * @param  list<MetaComision>  $metas
     * @param  list<ContratoComisionable>  $contratos
     * @param  list<AsesorComision>  $asesores
     * @return array{float, float}
     */
    private function logradoPorEquipo(MetaComision $meta, array $metas, array $contratos, array $asesores): array
    {
        $rolEquipo = AlcanceComisiones::EQUIPOS[$meta->rol] ?? null;
        $metaEquipo = null;

        foreach ($metas as $candidata) {
            if ($candidata->rol === $rolEquipo && $candidata->linea === $meta->linea && $candidata->atribucion->esPersonal()) {
                $metaEquipo = $candidata;
            }
        }

        if ($metaEquipo === null) {
            return [0.0, 0.0];
        }

        $integrantes = array_filter($asesores, fn (AsesorComision $asesor): bool => $asesor->rol === $rolEquipo);

        return $this->sumar($meta->linea, array_filter(
            $contratos,
            function (ContratoComisionable $contrato) use ($metaEquipo, $integrantes): bool {
                foreach ($integrantes as $integrante) {
                    if ($this->esDe($contrato, $metaEquipo->atribucion, $integrante)) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    private function esDe(ContratoComisionable $contrato, Atribucion $atribucion, AsesorComision $asesor): bool
    {
        return $this->mismoNombre($contrato->asesor($atribucion), $asesor);
    }

    private function mismoNombre(?string $nombre, AsesorComision $asesor): bool
    {
        return $nombre !== null
            && AsesoresComisiones::normalizar($nombre) === AsesoresComisiones::normalizar($asesor->codigoNetsuite);
    }

    /**
     * Cantidad y monto de los contratos de la línea. Las líneas de
     * AlcanceComisiones::LINEAS_POR_FILA cuentan filas en vez de contratos.
     *
     * @param  array<ContratoComisionable>  $contratos
     * @return array{float, float}
     */
    private function sumar(string $linea, array $contratos): array
    {
        $porFila = in_array($linea, AlcanceComisiones::LINEAS_POR_FILA, true);
        $cantidad = 0;
        $monto = 0.0;

        foreach ($contratos as $contrato) {
            if ($contrato->linea !== $linea) {
                continue;
            }

            $cantidad += $porFila ? $contrato->filas : 1;
            $monto += $contrato->monto;
        }

        return [(float) $cantidad, round($monto, 2)];
    }

    /**
     * Lo cobrado en los meses del trimestre, por colaborador y línea.
     *
     * @param  list<AsesorComision>  $asesores
     * @return array<string, float> "asesorId|linea" => comisión
     */
    private function adelantos(PeriodoComision $trimestre, array $asesores): array
    {
        $adelantos = [];

        for ($mes = 0; $mes < 3; $mes++) {
            $inicio = $trimestre->desde->addMonths($mes);
            $periodo = PeriodoComision::mes($inicio->year, $inicio->month);
            $metas = $this->metas->vigentes($periodo);
            $mensuales = $this->evaluarMetas($metas, $this->base->contratos($periodo), $this->montosNetos($periodo), $this->anualidadesMorosas($metas, $periodo), $asesores);

            foreach ($mensuales as $comision) {
                $clave = $comision->asesor->id.'|'.$comision->resultado->meta->linea;
                $adelantos[$clave] = round(($adelantos[$clave] ?? 0.0) + $comision->comision(), 2);
            }
        }

        return $adelantos;
    }

    /**
     * Nombres de las columnas de atribución personal que no coinciden con el
     * CODIGO_NETSUITE de nadie, con cuántos contratos tienen.
     *
     * @param  list<MetaComision>  $metas
     * @param  list<ContratoComisionable>  $contratos
     * @param  list<AnualidadMorosa>  $morosas
     * @param  list<AsesorComision>  $asesores
     * @return array<string, int> de más a menos contratos
     */
    private function nombresSinConfigurar(array $metas, array $contratos, array $morosas, array $asesores): array
    {
        $codigos = array_flip(array_map(
            fn (AsesorComision $asesor): string => AsesoresComisiones::normalizar($asesor->codigoNetsuite),
            $asesores,
        ));
        $sinConfigurar = [];

        foreach ($metas as $meta) {
            if (! $meta->atribucion->esPersonal()) {
                continue;
            }

            foreach ($contratos as $contrato) {
                $nombre = $contrato->asesor($meta->atribucion);

                if ($contrato->linea !== $meta->linea || $nombre === null || isset($codigos[AsesoresComisiones::normalizar($nombre)])) {
                    continue;
                }

                $sinConfigurar[$nombre][$contrato->linea.'|'.$contrato->contrato] = true;
            }
        }

        foreach ($morosas as $morosa) {
            if ($morosa->dueno !== null && ! isset($codigos[AsesoresComisiones::normalizar($morosa->dueno)])) {
                $sinConfigurar[$morosa->dueno]['MOROSO|'.$morosa->contrato.'|'.$morosa->servicio] = true;
            }
        }

        $conteo = array_map('count', $sinConfigurar);
        arsort($conteo);

        return $conteo;
    }
}
