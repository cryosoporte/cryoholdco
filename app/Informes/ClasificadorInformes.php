<?php

namespace App\Informes;

use App\Serologia\AlcanceSerologia;
use Carbon\CarbonImmutable;

/**
 * Las reglas de los filtros de Informes: a qué valor de cada filtro pertenece un contrato base y si cumple
 * la búsqueda. Las comparten el listado y las tarjetas, así que sus números siempre coinciden.
 */
class ClasificadorInformes
{
    private const SERVICIOS = ['sangre', 'tejido'];

    public function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(AlcanceSerologia::ZONA_HORARIA)->startOfDay();
    }

    /**
     * El valor de cada contrato base en cada dimensión de filtro (null si no tiene valor en ella).
     *
     * @param  array<string, mixed>  $fila
     * @return array{envio: string, liberacion: string, antiguedad: ?string, sangre: ?string, tejido: ?string}
     */
    public function valores(array $fila, CarbonImmutable $hoy): array
    {
        $enviado = (int) $fila['enviado'] === AlcanceInformes::FLAG_ENVIADO;
        $resultados = [];
        $liberado = true;

        foreach (self::SERVICIOS as $servicio) {
            $tiene = (int) $fila["tiene_{$servicio}"] === 1;
            $enInfo = (int) $fila["{$servicio}_en_info"] === 1;
            $resultados[$servicio] = match (LiberacionContrato::servicio($tiene, $enInfo, $fila["liberacion_{$servicio}"])) {
                LiberacionContrato::APTO => 'apto',
                LiberacionContrato::NO_APTO => 'no_apto',
                LiberacionContrato::NO_LIBERADO => 'no_liberado',
                default => null,
            };
            $liberado = $liberado && (! $tiene || $enInfo);
        }

        return [
            'envio' => $enviado ? 'enviado' : 'pendiente',
            'liberacion' => $liberado ? 'liberado' : 'no_liberado',
            'antiguedad' => $enviado ? null : $this->rangoDeAntiguedad($fila['fecha_colecta'], $hoy),
            'sangre' => $resultados['sangre'],
            'tejido' => $resultados['tejido'],
        ];
    }

    /**
     * El valor elegido en cada dimensión de filtro (null si no hay filtro en ella).
     *
     * @return array<string, ?string>
     */
    public function filtrosActivos(FiltrosInformes $filtros): array
    {
        return [
            'envio' => $filtros->envio,
            'liberacion' => $filtros->liberacion,
            'antiguedad' => $filtros->antiguedad,
            'sangre' => $filtros->sangre,
            'tejido' => $filtros->tejido,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public function coincideConLaBusqueda(array $fila, string $buscar): bool
    {
        if ($buscar === '') {
            return true;
        }

        $texto = mb_strtoupper($buscar);

        foreach (['nombre', 'identificacion', 'numero_base', 'apellido_titular1', 'apellido_titular2'] as $campo) {
            if (str_contains(mb_strtoupper((string) $fila[$campo]), $texto)) {
                return true;
            }
        }

        return false;
    }

    /**
     * La clave de AlcanceInformes::ANTIGUEDADES a la que pertenece la colecta, o null si no tiene fecha o es futura.
     */
    private function rangoDeAntiguedad(mixed $fechaColecta, CarbonImmutable $hoy): ?string
    {
        if ($fechaColecta === null) {
            return null;
        }

        $fecha = CarbonImmutable::createFromFormat('!Y-m-d', substr((string) $fechaColecta, 0, 10), AlcanceSerologia::ZONA_HORARIA);
        $dias = intdiv($hoy->getTimestamp() - $fecha->getTimestamp(), 86400);

        foreach (AlcanceInformes::ANTIGUEDADES as $clave => [$desde, $hasta]) {
            if ($dias >= $desde && ($hasta === null || $dias <= $hasta)) {
                return $clave;
            }
        }

        return null;
    }
}
