<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\FuenteDatos;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ComisionesController extends Controller
{
    private const FUENTE = FuenteFacturasOracle::CODIGO;

    public function index(): Response
    {
        $fuente = FuenteDatos::porCodigo(self::FUENTE);

        return Inertia::render('comisiones', [
            'fuente' => $fuente === null ? null : Arr::only(
                $fuente->datosActualizacion(),
                ['codigo', 'nombre', 'fecha_actualizacion', 'actualizado_por'],
            ),
        ]);
    }
}
