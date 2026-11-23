<?php

namespace App\Administracion;

use App\Models\User;
use Illuminate\Support\Collection;

final readonly class EstadoRolPersona
{
    private function __construct(
        public ?RolSistema $rol,
        public bool $incompleto,
    ) {}

    /**
     * @param  Collection<int, User>  $cuentas  Con la relación roles cargada.
     */
    public static function desdeCuentas(Collection $cuentas): self
    {
        $rolesPorCuenta = $cuentas->map(fn (User $cuenta): array => $cuenta->roles->pluck('rol')->all());
        $roles = $rolesPorCuenta->collapse();
        $rol = $roles->containsStrict(RolSistema::Administrador) ? RolSistema::Administrador : $roles->first();
        $rolesEsperados = $rol === null ? [] : [$rol];

        return new self(
            $rol,
            $rolesPorCuenta->contains(fn (array $rolesCuenta): bool => $rolesCuenta !== $rolesEsperados),
        );
    }
}
