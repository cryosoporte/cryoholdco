<?php

namespace App\Administracion;

use App\Models\EventoSeguridad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AsignarPermisosRol
{
    /** @param list<string> $permisos */
    public function ejecutar(User $autor, Rol $rol, array $permisos): void
    {
        $rol->getConnection()->transaction(function () use ($autor, $rol, $permisos): void {
            $actual = Rol::bloquear($rol->codigo);
            if (array_intersect($permisos, $actual->permisosExclusivosAjenos()) !== []) {
                throw ValidationException::withMessages(['permisos' => 'Solo el rol Administrador puede gestionar usuarios y roles.']);
            }

            $antes = $actual->permisos()->orderBy('permiso')->pluck('permiso')->all();
            $obsoletos = array_diff($antes, array_keys(CatalogoPermisos::PERMISOS));
            $despues = array_values(array_unique([...$permisos, ...$actual->permisosFijos(), ...$obsoletos]));
            sort($despues, SORT_STRING);

            $agregados = array_values(array_diff($despues, $antes));
            $quitados = array_values(array_diff($antes, $despues));
            if ($agregados === [] && $quitados === []) {
                return;
            }

            if ($quitados !== []) {
                $actual->permisos()->whereIn('permiso', $quitados)->delete();
            }
            if ($agregados !== []) {
                $actual->permisos()->insert(array_map(
                    fn (string $permiso): array => ['rol' => $actual->codigo, 'permiso' => $permiso],
                    $agregados,
                ));
            }

            EventoSeguridad::registrar(EventoSeguridad::PERMISOS_ROL, $autor, [
                'nombre' => $actual->nombre,
                ...compact('antes', 'despues', 'agregados', 'quitados'),
            ], $actual->codigo);
        });
    }
}
