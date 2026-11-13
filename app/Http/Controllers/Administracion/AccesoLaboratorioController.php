<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\ListadoAccesosLaboratorio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\ActualizarAccesoLaboratorioRequest;
use App\Http\Requests\Administracion\CrearAccesoLaboratorioRequest;
use App\Models\UsuarioLaboratorio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccesoLaboratorioController extends Controller
{
    public function index(ListadoAccesosLaboratorio $listado): Response
    {
        return Inertia::render('administracion-laboratorios', [
            'laboratorios' => $listado->consultar(),
        ]);
    }

    public function crear(CrearAccesoLaboratorioRequest $request): RedirectResponse
    {
        UsuarioLaboratorio::create($request->validated());

        return to_route('administracion.laboratorios');
    }

    public function actualizar(ActualizarAccesoLaboratorioRequest $request, UsuarioLaboratorio $acceso): RedirectResponse
    {
        $datos = $request->validated();

        $acceso->estado = $datos['estado'];

        if (filled($datos['password'] ?? null)) {
            $acceso->password = $datos['password'];
        }

        $acceso->save();

        return to_route('administracion.laboratorios');
    }
}
