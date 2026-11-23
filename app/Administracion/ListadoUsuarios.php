<?php

namespace App\Administracion;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * @phpstan-type PersonaUsuario array{id: int, nombre: string, correos: list<string>, dni: string, cuentas: int, rol: value-of<RolSistema>|null, rol_incompleto: bool, es_usted: bool}
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

    /**
     * @param  Collection<int, User>  $cuentas
     * @return PersonaUsuario
     */
    private function persona(Collection $cuentas, bool $esUsted): array
    {
        $representante = $cuentas->first();
        $estadoRol = EstadoRolPersona::desdeCuentas($cuentas);

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
            'rol' => $estadoRol->rol?->value,
            'rol_incompleto' => $estadoRol->incompleto,
            'es_usted' => $esUsted,
        ];
    }

    /** @param PersonaUsuario $persona */
    private function ordenRol(array $persona): int
    {
        return match (true) {
            $persona['rol_incompleto'] => 2,
            $persona['rol'] === RolSistema::Administrador->value => 0,
            $persona['rol'] === RolSistema::Gerencia->value => 1,
            default => 3,
        };
    }
}
