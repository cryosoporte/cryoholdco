<?php

namespace App\Informes;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Los contratos base de Informes de una empresa tal como salen de Oracle, guardados unos segundos en caché.
 * El listado y las tarjetas del resumen se calculan sobre estas mismas filas, así que cargan juntos, rápido
 * y siempre cuadran entre sí. Se renueva solo al pasar el tiempo o al registrar un envío desde la aplicación.
 */
#[Singleton]
class FilasInformes
{
    public const SEGUNDOS_EN_CACHE = 60;

    /** @var array<string, list<array<string, mixed>>> */
    private array $enMemoria = [];

    public function __construct(private readonly ContratosPorBase $contratos) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function de(string $empresa): array
    {
        return $this->enMemoria[$empresa] ??= Cache::remember(
            $this->clave($empresa),
            self::SEGUNDOS_EN_CACHE,
            fn (): array => DB::query()
                ->fromSub($this->contratos->consulta($empresa), 'resultados')
                ->get()
                ->map(fn (object $fila): array => (array) $fila)
                ->all(),
        );
    }

    public function olvidar(string $empresa): void
    {
        unset($this->enMemoria[$empresa]);
        Cache::forget($this->clave($empresa));
    }

    private function clave(string $empresa): string
    {
        return "informes.filas.{$empresa}";
    }
}
