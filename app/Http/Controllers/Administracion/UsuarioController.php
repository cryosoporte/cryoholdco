<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\AsignarRolesPersona;
use App\Administracion\ListadoUsuarios;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\AsignarRolesUsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(Request $request, ListadoUsuarios $listado): Response
    {
        return Inertia::render('administracion-usuarios', [
            'personas' => $listado->consultar($request->user()),
            'rolesDisponibles' => $listado->rolesDisponibles(),
        ]);
    }

    public function asignarRoles(AsignarRolesUsuarioRequest $request, User $usuario, AsignarRolesPersona $asignacion): RedirectResponse
    {
        $asignacion->asignar($request->user(), $usuario, $request->validated('roles'));

        return to_route('administracion.usuarios');
    }
}
