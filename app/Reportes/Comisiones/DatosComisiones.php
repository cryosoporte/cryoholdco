<?php

namespace App\Reportes\Comisiones;

/**
 * De dónde salen las liquidaciones que muestra la pantalla de Comisiones:
 * los datos de ejemplo o el cálculo real (reportes.comisiones_demo.datos).
 *
 * @phpstan-import-type Resumen from DatosEjemploComisiones
 * @phpstan-import-type Liquidacion from DatosEjemploComisiones
 * @phpstan-import-type Asesor from DatosEjemploComisiones
 * @phpstan-import-type Envio from DatosEjemploComisiones
 */
interface DatosComisiones
{
    /**
     * Período que se muestra si todavía no se generó una liquidación.
     */
    public function periodoInicial(): string;

    /** @return list<array{id: string, etiqueta: string}> */
    public function periodosDisponibles(): array;

    /** @return list<Resumen> la activa primero */
    public function liquidaciones(string $activa): array;

    /** @return Liquidacion|null */
    public function liquidacion(string $id, string $activa): ?array;

    /**
     * @param  Asesor  $asesor
     * @return Envio
     */
    public function envioPendiente(array $asesor): array;
}
