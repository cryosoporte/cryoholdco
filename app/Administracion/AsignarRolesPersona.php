<?php

namespace App\Administracion;

use App\Models\EventoSeguridad;
use App\Models\Rol;
use App\Models\User;
use App\Models\UsuarioRol;
use Illuminate\Validation\ValidationException;

class AsignarRolesPersona
{
    /**
     * @param  list<string>  $roles
     * @return array{cambio: bool}
     */
    public function asignar(User $autor, User $persona, array $roles): array
    {
        $asignacion = new UsuarioRol;
        $conexion = $asignacion->getConnection();
        $tabla = $asignacion->getTable();
        $roles = array_values(array_unique($roles));
        sort($roles, SORT_STRING);

        return $conexion->transaction(function () use ($autor, $persona, $roles, $asignacion, $conexion, $tabla): array {
            if ($autor->clavePersona() === $persona->clavePersona()) {
                throw ValidationException::withMessages(['roles' => 'No puedes cambiar tus propios roles.']);
            }

            $asignacion->bloquearAsignaciones();
            if ($roles !== [] && Rol::query()->whereIn('codigo', $roles)->count() !== count($roles)) {
                throw ValidationException::withMessages(['roles' => 'Elige roles válidos.']);
            }

            $cuentas = $persona->filasDeLaPersona()->load('roles:user_id,rol');
            $estadoAnterior = EstadoRolesPersona::desdeCuentas($cuentas);
            if (! $estadoAnterior->incompleto && $estadoAnterior->roles === $roles) {
                return ['cambio' => false];
            }

            $nuevasAsignaciones = [];
            foreach ($estadoAnterior->rolesPorCuenta as $id => $rolesCuenta) {
                $rolesQuitados = array_diff($rolesCuenta, $roles);
                if ($rolesQuitados !== []) {
                    $conexion->table($tabla)->where('user_id', $id)->whereIn('rol', $rolesQuitados)->delete();
                }

                foreach (array_diff($roles, $rolesCuenta) as $codigo) {
                    $nuevasAsignaciones[] = ['user_id' => $id, 'rol' => $codigo];
                }
            }
            if ($nuevasAsignaciones !== []) {
                $conexion->table($tabla)->insert($nuevasAsignaciones);
            }

            $administradores = $conexion->table($tabla)->where('rol', Rol::ADMINISTRADOR)->select('user_id');
            if (! User::query()->whereIn('id', $administradores)->exists()) {
                throw ValidationException::withMessages(['roles' => 'Debe quedar al menos una persona con rol Administrador.']);
            }

            $ids = $cuentas->modelKeys();
            EventoSeguridad::registrar(EventoSeguridad::ROLES_USUARIO, $autor, [
                'autor_correo' => $autor->email,
                'persona' => [
                    'ids' => $ids,
                    'correos' => $cuentas->pluck('email')->uniqueStrict()->values()->all(),
                    'nombre' => $cuentas->first()->name,
                ],
                'roles_incompletos' => $estadoAnterior->incompleto,
                'roles_anteriores' => $estadoAnterior->rolesPorCuenta,
                'roles_nuevos' => $roles,
            ], count($roles) === 1 ? $roles[0] : null, $ids);

            return ['cambio' => true];
        });
    }
}
