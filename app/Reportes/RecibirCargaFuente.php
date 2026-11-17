<?php

namespace App\Reportes;

use App\Jobs\ImportarFuenteJob;
use App\Models\FuenteCarga;
use App\Models\FuenteDatos;
use App\Models\User;
use App\Reportes\Fuentes\Fuente;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RecibirCargaFuente
{
    public function recibir(Fuente $fuente, UploadedFile $archivo, User $usuario): void
    {
        $this->validarSinCargaActiva($fuente);
        $ruta = 'fuentes/'.Str::uuid().'.xlsx';

        $carga = DB::transaction(function () use ($fuente, $archivo, $usuario, $ruta): FuenteCarga {
            if (FuenteDatos::query()->whereKey($fuente->codigo())->lockForUpdate()->get(['codigo'])->isEmpty()) {
                throw (new ModelNotFoundException)->setModel(FuenteDatos::class, [$fuente->codigo()]);
            }

            $this->validarSinCargaActiva($fuente);

            return FuenteCarga::query()->forceCreate([
                'codigo_fuente' => $fuente->codigo(),
                'estado' => FuenteCarga::PENDIENTE,
                'archivo' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'ruta_archivo' => $ruta,
                'cargado_por' => mb_substr($usuario->name, 0, 100),
                'usuario_id' => (string) $usuario->getAuthIdentifier(),
                'fecha_recepcion' => now(),
            ]);
        });

        try {
            if ($archivo->storeAs('fuentes', basename($ruta), 'local') === false) {
                throw new RuntimeException('No se pudo guardar el archivo temporal de la fuente.');
            }

            Bus::dispatch(new ImportarFuenteJob($carga->id, $ruta));
        } catch (Throwable $error) {
            report($error);

            $mensaje = 'No se pudo preparar la importación. Intenta nuevamente.';

            if ($carga->fallar(FuenteCarga::PENDIENTE, $mensaje)) {
                $carga->eliminarTemporal();

                throw ValidationException::withMessages(['archivo' => $mensaje]);
            }
        }
    }

    private function validarSinCargaActiva(Fuente $fuente): void
    {
        if (FuenteCarga::query()->where('codigo_fuente', $fuente->codigo())->whereIn('estado', FuenteCarga::ACTIVAS)->exists()) {
            throw ValidationException::withMessages(['archivo' => 'Ya hay una carga en proceso para esta fuente.']);
        }
    }
}
