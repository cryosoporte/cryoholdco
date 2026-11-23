<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\AsignarRolPersona;
use App\Administracion\ListadoUsuarios;
use App\Administracion\RolSistema;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\AsignarRolUsuarioRequest;
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
        ]);
    }

    public function asignarRol(AsignarRolUsuarioRequest $request, User $usuario, AsignarRolPersona $asignacion): RedirectResponse
    {
        $asignacion->asignar($request->user(), $usuario, $request->enum('rol', RolSistema::class));

        return to_route('administracion.usuarios');
    }
}
