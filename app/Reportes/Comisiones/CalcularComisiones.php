<?php

namespace App\Reportes\Comisiones;

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
 * - empresa o clínica: todos los contratos de la línea.
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
        $comisiones = $this->evaluarMetas($metas, $contratos, $asesores);

        if ($periodo->periodicidad === Periodicidad::Trimestral) {
            $adelantos = $this->adelantos($periodo, $asesores);
            $comisiones = array_map(fn (ComisionCalculada $comision): ComisionCalculada => new ComisionCalculada(
                $comision->asesor,
                $comision->resultado,
                $adelantos[$comision->asesor->id.'|'.$comision->resultado->meta->linea] ?? 0.0,
            ), $comisiones);
        }

        return new ResultadoComisiones(
            periodo: $periodo,
            comisiones: $comisiones,
            nombresSinConfigurar: $this->nombresSinConfigurar($metas, $contratos, $asesores),
            serviciosSinLinea: $this->base->serviciosSinLinea($periodo),
            filasBase: array_sum(array_map(fn (ContratoComisionable $contrato): int => $contrato->filas, $contratos)),
        );
    }

    /**
     * @param  list<MetaComision>  $metas
     * @param  list<ContratoComisionable>  $contratos
     * @param  list<AsesorComision>  $asesores
     * @return list<ComisionCalculada>
     */
    private function evaluarMetas(array $metas, array $contratos, array $asesores): array
    {
        $comisiones = [];

        foreach ($metas as $meta) {
            foreach ($asesores as $asesor) {
                if ($asesor->rol !== $meta->rol) {
                    continue;
                }

                [$cantidad, $monto] = $this->logrado($meta, $asesor, $metas, $contratos, $asesores);
                $comisiones[] = new ComisionCalculada($asesor, $meta->evaluar($cantidad, $monto));
            }
        }

        return $comisiones;
    }

    /**
     * @param  list<MetaComision>  $metas
     * @param  list<ContratoComisionable>  $contratos
     * @param  list<AsesorComision>  $asesores
     * @return array{float, float} cantidad y monto
     */
    private function logrado(MetaComision $meta, AsesorComision $asesor, array $metas, array $contratos, array $asesores): array
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

        return $this->sumar($meta->linea, $contratos);
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
        $nombre = $contrato->asesor($atribucion);

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
            $mensuales = $this->evaluarMetas($this->metas->vigentes($periodo), $this->base->contratos($periodo), $asesores);

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
     * @param  list<AsesorComision>  $asesores
     * @return array<string, int> de más a menos contratos
     */
    private function nombresSinConfigurar(array $metas, array $contratos, array $asesores): array
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

        $conteo = array_map('count', $sinConfigurar);
        arsort($conteo);

        return $conteo;
    }
}
