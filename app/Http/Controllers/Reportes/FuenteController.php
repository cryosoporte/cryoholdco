<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\CargarFuenteRequest;
use App\Models\FuenteCarga;
use App\Models\FuenteDatos;
use App\Reportes\Fuentes\CatalogoFuentes;
use App\Reportes\Fuentes\Fuente;
use App\Reportes\ImportarFuente;
use App\Reportes\RecibirCargaFuente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FuenteController extends Controller
{
    /**
     * Las fuentes del catálogo que tienen su fila en la maestra, con su
     * última actualización.
     */
    public function index(Request $request, CatalogoFuentes $catalogo): Response
    {
        $codigos = array_map(fn (Fuente $fuente): string => $fuente->codigo(), $catalogo->todas());
        $ultimasCargas = config('reportes.cargas_asincronas') ? FuenteCarga::ultimasPorFuente($codigos) : collect();
        $registradas = FuenteDatos::query()->whereIn('codigo', $codigos)->get()->keyBy('codigo');
        $usuarioId = (string) $request->user()->getAuthIdentifier();

        $fuentes = collect($codigos)
            ->map(fn (string $codigo): ?FuenteDatos => $registradas->get($codigo))
            ->filter()
            ->map(fn (FuenteDatos $fuente): array => [
                ...$fuente->datosActualizacion(),
                'ultima_carga' => $ultimasCargas->get($fuente->codigo)?->resultado($usuarioId),
            ])
            ->values();

        return Inertia::render('reportes-fuentes', [
            'fuentes' => $fuentes,
        ]);
    }

    public function cargar(
        CargarFuenteRequest $request,
        string $fuente,
        CatalogoFuentes $catalogo,
        ImportarFuente $importador,
        RecibirCargaFuente $recibirCarga,
    ): RedirectResponse {
        $definicion = $catalogo->buscar($fuente) ?? abort(404);
        $maestra = FuenteDatos::porCodigo($definicion->codigo()) ?? abort(404);
        $archivo = $request->file('archivo');

        if (config('reportes.cargas_asincronas')) {
            $recibirCarga->recibir($definicion, $archivo, $request->user());
            Inertia::flash('cargaFuenteRecibida', $maestra->nombre);

            return to_route('reportes.fuentes');
        }

        $filas = $importador->importar(
            $definicion,
            $archivo->getRealPath(),
            $request->user()->name,
            $archivo->getClientOriginalName(),
        );

        Inertia::flash('fuenteCargada', [
            'fuente' => $maestra->nombre,
            'filas' => $filas,
        ]);

        return to_route('reportes.fuentes');
    }
}
