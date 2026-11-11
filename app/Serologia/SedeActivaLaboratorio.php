<?php

namespace App\Serologia;

use App\Models\Sede;
use Illuminate\Contracts\Session\Session;

/**
 * El laboratorio y la sede desde donde trabaja el mostrador, guardados en la sesión.
 *
 * Mientras no exista un inicio de sesión para laboratorios, se eligen a mano en la pantalla.
 */
class SedeActivaLaboratorio
{
    public const LABORATORIO_POR_DEFECTO = '20330025213';

    public function __construct(private readonly Session $session) {}

    /**
     * El laboratorio y la sede vigentes. Si el laboratorio guardado no tiene sedes activas
     * se usa el primero que tenga; si la sede guardada no es válida, la primera de ese laboratorio.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function actual(): array
    {
        $laboratorio = $this->session->get('laboratorio.ruc', self::LABORATORIO_POR_DEFECTO);
        $sedes = Sede::deLaboratorio($laboratorio);

        if ($sedes->isEmpty()) {
            $laboratorio = Sede::laboratorios()->first()?->identificacion;
            $sedes = $laboratorio ? Sede::deLaboratorio($laboratorio) : collect();
        }

        $sede = $this->session->get('laboratorio.sede');

        if (! $sedes->firstWhere('codigo_sede', $sede)) {
            $sede = $sedes->first()?->codigo_sede;
        }

        return [$laboratorio, $sede];
    }

    /**
     * Guarda el laboratorio y la sede elegidos. Si la sede no pertenece al laboratorio
     * se guarda su primera sede activa. Devuelve false, sin cambiar nada, si el
     * laboratorio no tiene sedes activas.
     */
    public function elegir(string $laboratorio, ?string $sede): bool
    {
        $sedes = Sede::deLaboratorio($laboratorio);

        if ($sedes->isEmpty()) {
            return false;
        }

        $sede = $sedes->firstWhere('codigo_sede', $sede) ? $sede : $sedes->first()->codigo_sede;

        $this->session->put([
            'laboratorio.ruc' => $laboratorio,
            'laboratorio.sede' => $sede,
        ]);

        return true;
    }
}
