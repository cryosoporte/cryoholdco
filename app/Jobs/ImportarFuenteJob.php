<?php

namespace App\Jobs;

use App\Models\FuenteCarga;
use App\Reportes\Fuentes\CatalogoFuentes;
use App\Reportes\ImportarFuente;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImportarFuenteJob implements ShouldQueue
{
    use Queueable;

    public const TIEMPO_LIMITE = 900;

    public int $timeout = self::TIEMPO_LIMITE;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $cargaId, public string $ruta)
    {
        $this->onConnection('redis_reportes');
    }

    public function handle(CatalogoFuentes $catalogo, ImportarFuente $importador): void
    {
        $carga = $this->carga();

        if ($carga === null || ! $carga->iniciar()) {
            return;
        }

        try {
            $fuente = $catalogo->buscar($carga->codigo_fuente)
                ?? throw new RuntimeException('La fuente ya no está registrada en el catálogo.');

            $importador->importar(
                $fuente,
                Storage::disk('local')->path($this->ruta),
                $carga->cargado_por,
                $carga->archivo,
                $carga,
            );
        } catch (ValidationException $error) {
            $carga->fallar(FuenteCarga::EN_PROCESO, $error->errors()['archivo'][0] ?? FuenteCarga::ERROR_IMPORTACION);
        } catch (Throwable $error) {
            report($error);
            $carga->fallar(FuenteCarga::EN_PROCESO, FuenteCarga::ERROR_IMPORTACION);
        }

        $carga->eliminarTemporal();
    }

    public function failed(?Throwable $error): void
    {
        $carga = $this->carga();

        if ($carga !== null
            && in_array($carga->estado, FuenteCarga::ACTIVAS, true)
            && $carga->fallar($carga->estado, FuenteCarga::ERROR_IMPORTACION)) {
            $carga->eliminarTemporal();
        }
    }

    private function carga(): ?FuenteCarga
    {
        return FuenteCarga::query()->where('ruta_archivo', $this->ruta)->find($this->cargaId);
    }
}
