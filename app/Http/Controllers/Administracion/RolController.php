<?php

namespace App\Http\Controllers\Administracion;

use App\Administracion\AsignarPermisosRol;
use App\Administracion\EliminarRol;
use App\Administracion\GuardarRol;
use App\Administracion\ListadoRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\AsignarPermisosRolRequest;
use App\Http\Requests\Administracion\GuardarRolRequest;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolController extends Controller
{
    public function index(ListadoRoles $listado): Response
    {
        return Inertia::render('administracion-roles', ['rolesRegistrados' => $listado->consultar()]);
    }

    public function crear(GuardarRolRequest $request, GuardarRol $guardar): RedirectResponse
    {
        $rol = $guardar->ejecutar($request->user(), $request->validated('nombre'), $request->validated('descripcion'));

        return to_route('administracion.roles.mostrar', $rol);
    }

    public function mostrar(Rol $rol, ListadoRoles $listado): Response
    {
        return Inertia::render('administracion-rol', $listado->mostrar($rol));
    }

    public function actualizar(GuardarRolRequest $request, Rol $rol, GuardarRol $guardar): RedirectResponse
    {
        $guardar->ejecutar($request->user(), $request->validated('nombre'), $request->validated('descripcion'), $rol);

        return to_route('administracion.roles');
    }

    public function permisos(AsignarPermisosRolRequest $request, Rol $rol, AsignarPermisosRol $asignar): RedirectResponse
    {
        $asignar->ejecutar($request->user(), $rol, $request->validated('permisos'));

        return to_route('administracion.roles.mostrar', $rol);
    }

    public function eliminar(Request $request, Rol $rol, EliminarRol $eliminar): RedirectResponse
    {
        $eliminar->ejecutar($request->user(), $rol);

        return to_route('administracion.roles');
    }
}
