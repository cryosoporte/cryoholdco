<?php

namespace App\Administracion;

use App\Models\User;
use App\Models\UsuarioRol;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AsignarRolPersona
{
    /** @return array{cambio: bool} */
    public function asignar(User $autor, User $persona, ?RolSistema $rol): array
    {
        $asignacion = new UsuarioRol;
        $conexion = $asignacion->getConnection();
        $tabla = $asignacion->getTable();

        return $conexion->transaction(function () use ($autor, $persona, $rol, $conexion, $tabla): array {
            if ($autor->clavePersona() === $persona->clavePersona()) {
                throw ValidationException::withMessages(['rol' => 'No puedes cambiar tu propio rol.']);
            }

            if ($conexion->getDriverName() === 'oracle') {
                $tablaOracle = $conexion->getQueryGrammar()->wrapTable($tabla);
                $conexion->statement("LOCK TABLE {$tablaOracle} IN EXCLUSIVE MODE");
            }

            $cuentas = $persona->filasDeLaPersona()->load('roles:user_id,rol');
            $estadoAnterior = EstadoRolPersona::desdeCuentas($cuentas);
            if (! $estadoAnterior->incompleto && $estadoAnterior->rol === $rol) {
                return ['cambio' => false];
            }

            $ids = $cuentas->modelKeys();
            $conexion->table($tabla)->whereIn('user_id', $ids)->delete();

            if ($rol !== null) {
                $conexion->table($tabla)->insert(array_map(
                    fn (int $id): array => ['user_id' => $id, 'rol' => $rol->value],
                    $ids,
                ));
            }

            if (! $conexion->table($tabla)->where('rol', RolSistema::Administrador->value)->exists()) {
                throw ValidationException::withMessages(['rol' => 'Debe quedar al menos una persona con rol Administrador.']);
            }

            $contexto = [
                'autor' => $autor->only(['id', 'email', 'empresa']),
                'persona' => ['ids' => $ids, 'correos' => $cuentas->pluck('email')->uniqueStrict()->values()->all()],
                'rol_anterior' => $estadoAnterior->incompleto ? 'incompleto' : $estadoAnterior->rol?->value,
                'rol_nuevo' => $rol?->value,
            ];
            $conexion->afterCommit(fn () => Log::info('Rol de usuario actualizado', $contexto));

            return ['cambio' => true];
        });
    }
}
