<?php

namespace App\Administracion;

use App\Models\EventoSeguridad;
use App\Models\Rol;
use App\Models\User;
use App\Models\UsuarioRol;
use Illuminate\Validation\ValidationException;

class EliminarRol
{
    public function ejecutar(User $autor, Rol $rol): void
    {
        $rol->getConnection()->transaction(function () use ($autor, $rol): void {
            (new UsuarioRol)->bloquearAsignaciones();
            $actual = Rol::bloquear($rol->codigo);

            if (($impedimento = $actual->impedimentoParaEliminar()) !== null) {
                throw ValidationException::withMessages(['rol' => $impedimento]);
            }

            $antes = [
                ...$actual->only(['nombre', 'descripcion']),
                'permisos' => $actual->permisos()->orderBy('permiso')->pluck('permiso')->all(),
            ];
            $actual->permisos()->delete();
            $actual->delete();

            EventoSeguridad::registrar(EventoSeguridad::ROL_ELIMINADO, $autor, [
                'antes' => $antes,
                'despues' => null,
            ], $actual->codigo);
        });
    }
}
