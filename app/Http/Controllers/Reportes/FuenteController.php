<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\CargarFuenteRequest;
use App\Models\FuenteDatos;
use App\Reportes\Fuentes\CatalogoFuentes;
use App\Reportes\Fuentes\Fuente;
use App\Reportes\ImportarFuente;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FuenteController extends Controller
{
    /**
     * Las fuentes del catálogo que tienen su fila en la maestra, con su
     * última actualización.
     */
    public function index(CatalogoFuentes $catalogo): Response
    {
        $codigos = array_map(fn (Fuente $fuente): string => $fuente->codigo(), $catalogo->todas());
        $registradas = FuenteDatos::query()->whereIn('codigo', $codigos)->get()->keyBy('codigo');

        $fuentes = collect($codigos)
            ->map(fn (string $codigo): ?array => $registradas->get($codigo)?->datosActualizacion())
            ->filter()
            ->values();

        return Inertia::render('reportes-fuentes', [
            'fuentes' => $fuentes,
        ]);
    }

    public function cargar(CargarFuenteRequest $request, string $fuente, CatalogoFuentes $catalogo, ImportarFuente $importador): RedirectResponse
    {
        $definicion = $catalogo->buscar($fuente) ?? abort(404);
        $maestra = FuenteDatos::porCodigo($definicion->codigo()) ?? abort(404);
        $archivo = $request->file('archivo');

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
