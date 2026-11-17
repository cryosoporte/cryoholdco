<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\CargarDataTestRequest;
use App\Reportes\ImportarPruebaComisiones;
use App\Reportes\LectorCsvNetsuite;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComisionesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('comisiones');
    }

    public function cargar(CargarDataTestRequest $request, LectorCsvNetsuite $lector, ImportarPruebaComisiones $importador): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $guardadas = $importador->importar($lector->leer($archivo->getRealPath()));

        Inertia::flash('carga', [
            'archivo' => $archivo->getClientOriginalName(),
            'filas' => $guardadas,
        ]);

        return to_route('reportes.comisiones');
    }
}
