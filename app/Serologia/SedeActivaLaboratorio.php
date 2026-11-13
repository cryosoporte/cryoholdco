<?php

namespace App\Serologia;

use App\Models\Sede;
use App\Models\UsuarioLaboratorio;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Session\Session;

/**
 * La sede desde donde trabaja el mostrador. El laboratorio es el que inició sesión;
 * la sede la elige en la pantalla entre sus sedes activas y se guarda en la sesión.
 */
class SedeActivaLaboratorio
{
    public function __construct(
        private readonly Session $session,
        #[CurrentUser('laboratorio')] private readonly UsuarioLaboratorio $laboratorio,
    ) {}

    /**
     * El RUC del laboratorio y la sede vigente. Si la sede guardada ya no está activa
     * se usa la primera sede activa del laboratorio.
     *
     * @return array{0: string, 1: ?string}
     */
    public function actual(): array
    {
        $ruc = $this->laboratorio->identificacion;
        $sedes = Sede::deLaboratorio($ruc);
        $sede = $this->session->get('laboratorio.sede');

        if (! $sedes->firstWhere('codigo_sede', $sede)) {
            $sede = $sedes->first()?->codigo_sede;
        }

        return [$ruc, $sede];
    }

    /**
     * Guarda la sede elegida. Devuelve false, sin cambiar nada, si no es una
     * sede activa del laboratorio.
     */
    public function elegir(string $sede): bool
    {
        if (! Sede::deLaboratorio($this->laboratorio->identificacion)->firstWhere('codigo_sede', $sede)) {
            return false;
        }

        $this->session->put('laboratorio.sede', $sede);

        return true;
    }
}
