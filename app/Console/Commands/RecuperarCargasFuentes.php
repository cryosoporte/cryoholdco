<?php

namespace App\Console\Commands;

use App\Jobs\ImportarFuenteJob;
use App\Models\FuenteCarga;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reportes:recuperar-cargas')]
#[Description('Cierra las importaciones de fuentes vencidas y elimina sus archivos temporales')]
class RecuperarCargasFuentes extends Command
{
    public function handle(): int
    {
        if (! config('reportes.cargas_asincronas')) {
            return self::SUCCESS;
        }

        $this->vencerCargas(
            FuenteCarga::PENDIENTE,
            'fecha_recepcion',
            30 * 60,
            'La importación no comenzó a tiempo. Vuelve a subir el archivo.',
        );

        $this->vencerCargas(
            FuenteCarga::EN_PROCESO,
            'fecha_inicio',
            ImportarFuenteJob::TIEMPO_LIMITE + 120,
            'La importación se interrumpió. Vuelve a subir el archivo.',
        );

        foreach (FuenteCarga::query()->whereIn('estado', [FuenteCarga::COMPLETADA, FuenteCarga::FALLIDA])
            ->whereNotNull('ruta_archivo')->lazyById() as $carga) {
            $carga->eliminarTemporal();
        }

        return self::SUCCESS;
    }

    private function vencerCargas(string $estado, string $columna, int $segundos, string $mensaje): void
    {
        $limite = FuenteCarga::ahora()->subSeconds($segundos);

        foreach (FuenteCarga::query()->where('estado', $estado)->where($columna, '<=', $limite)->lazyById() as $carga) {
            $carga->fallar($estado, $mensaje);
        }
    }
}
