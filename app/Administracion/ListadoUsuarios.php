<?php

namespace App\Administracion;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * @phpstan-type PersonaUsuario array{id: int, nombre: string, correos: list<string>, dni: string, cuentas: int, roles: list<string>, roles_incompletos: bool, es_usted: bool}
 */
class ListadoUsuarios
{
    /** @return Collection<int, PersonaUsuario> */
    public function consultar(User $usuario): Collection
    {
        $claveUsuario = $usuario->clavePersona();

        return User::query()
            ->with('roles:user_id,rol')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'identificacion', 'empresa'])
            ->groupBy(fn (User $cuenta): string => $cuenta->clavePersona())
            ->map(fn (Collection $cuentas, string $clave): array => $this->persona($cuentas, $clave === $claveUsuario))
            ->sortBy([
                fn (array $a, array $b): int => $this->ordenRol($a) <=> $this->ordenRol($b),
                fn (array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']),
            ])
            ->values();
    }

    /** @return list<array{codigo: string, nombre: string, descripcion: ?string}> */
    public function rolesDisponibles(): array
    {
        return Rol::query()
            ->orderByRaw('LOWER(nombre)')
            ->orderBy('codigo')
            ->get(['codigo', 'nombre', 'descripcion'])
            ->toArray();
    }

    /**
     * @param  Collection<int, User>  $cuentas
     * @return PersonaUsuario
     */
    private function persona(Collection $cuentas, bool $esUsted): array
    {
        $representante = $cuentas->first();
        $estadoRoles = EstadoRolesPersona::desdeCuentas($cuentas);

        $correos = $cuentas->map(fn (User $cuenta): string => mb_strtolower(trim($cuenta->email)))
            ->uniqueStrict()
            ->values()
            ->all();

        return [
            'id' => (int) $representante->id,
            'nombre' => $representante->name,
            'correos' => $correos,
            'dni' => trim((string) $representante->identificacion),
            'cuentas' => $cuentas->count(),
            'roles' => $estadoRoles->roles,
            'roles_incompletos' => $estadoRoles->incompleto,
            'es_usted' => $esUsted,
        ];
    }

    /** @param PersonaUsuario $persona */
    private function ordenRol(array $persona): int
    {
        return match (true) {
            in_array(Rol::ADMINISTRADOR, $persona['roles'], true) => 0,
            $persona['roles'] !== [] => 1,
            default => 2,
        };
    }
}
