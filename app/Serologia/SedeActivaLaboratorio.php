<?php

namespace App\Serologia;

use App\Models\Sede;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
<<<<<<< Updated upstream
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
=======
 * La sede desde donde trabaja el mostrador. El laboratorio es el que inició sesión;
 * la sede la elige en la pantalla entre sus sedes activas y se guarda en la sesión.
 *
 * Con una sola sede activa no hay nada que decidir y se usa esa. Con varias, el
 * laboratorio tiene que elegir antes de entrar al mostrador.
 */
class SedeActivaLaboratorio
{
    /** @var ?Collection<int, Sede> */
    private ?Collection $sedes = null;

    public function __construct(
        private readonly Session $session,
        #[CurrentUser('laboratorio')] private readonly UsuarioLaboratorio $laboratorio,
    ) {}

    /**
     * El RUC del laboratorio y la sede vigente. La sede es null mientras el
     * laboratorio con varias sedes no haya elegido una, y cuando se quedó sin
     * sedes activas.
>>>>>>> Stashed changes
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function actual(): array
    {
<<<<<<< Updated upstream
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
=======
        return [$this->laboratorio->identificacion, $this->sede()];
    }

    /**
     * Si hay que pedirle que elija: tiene sedes activas y ninguna vigente.
     */
    public function debeElegir(): bool
    {
        return $this->sede() === null && $this->sedes()->isNotEmpty();
>>>>>>> Stashed changes
    }

    /**
     * Guarda el laboratorio y la sede elegidos. Si la sede no pertenece al laboratorio
     * se guarda su primera sede activa. Devuelve false, sin cambiar nada, si el
     * laboratorio no tiene sedes activas.
     */
    public function elegir(string $laboratorio, ?string $sede): bool
    {
<<<<<<< Updated upstream
        $sedes = Sede::deLaboratorio($laboratorio);

        if ($sedes->isEmpty()) {
=======
        if (! $this->sedes()->firstWhere('codigo_sede', $sede)) {
>>>>>>> Stashed changes
            return false;
        }

        $sede = $sedes->firstWhere('codigo_sede', $sede) ? $sede : $sedes->first()->codigo_sede;

        $this->session->put([
            'laboratorio.ruc' => $laboratorio,
            'laboratorio.sede' => $sede,
        ]);

        return true;
    }

    /**
     * Las sedes activas del laboratorio, como las muestra la pantalla.
     *
     * @return array<int, array{codigo: string, nombre: ?string, direccion: ?string, ciudad: ?string}>
     */
    public function opciones(): array
    {
        return $this->sedes()->map(fn (Sede $fila): array => [
            'codigo' => $fila->codigo_sede,
            'nombre' => $fila->descripcion_sede,
            'direccion' => $fila->direccion_sede,
            'ciudad' => $fila->ciudad,
        ])->all();
    }

    /**
     * La sede guardada mientras siga activa. Si el laboratorio tiene una sola sede
     * activa se usa esa, porque no hay nada que elegir.
     */
    private function sede(): ?string
    {
        $sede = $this->session->get('laboratorio.sede');

        if (is_string($sede) && $this->sedes()->firstWhere('codigo_sede', $sede)) {
            return $sede;
        }

        return $this->sedes()->count() === 1 ? $this->sedes()->first()->codigo_sede : null;
    }

    /** @return Collection<int, Sede> */
    private function sedes(): Collection
    {
        return $this->sedes ??= Sede::deLaboratorio($this->laboratorio->identificacion);
    }
}
