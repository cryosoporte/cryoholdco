<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\ListadoEventosSeguridad;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HistorialSeguridadController extends Controller
{
    public function index(ListadoEventosSeguridad $listado): Response
    {
        return Inertia::render('administracion-historial', [
            'eventos' => $listado->consultar(),
            'tipos' => ListadoEventosSeguridad::TIPOS,
        ]);
    }
}
