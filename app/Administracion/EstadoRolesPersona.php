<?php

namespace App\Administracion;

use App\Models\User;
use Illuminate\Support\Collection;

final readonly class EstadoRolesPersona
{
    /**
     * @param  list<string>  $roles
     * @param  array<int, list<string>>  $rolesPorCuenta
     */
    private function __construct(
        public array $roles,
        public bool $incompleto,
        public array $rolesPorCuenta,
    ) {}

    /**
     * @param  Collection<int, User>  $cuentas  Con la relación roles cargada.
     */
    public static function desdeCuentas(Collection $cuentas): self
    {
        $rolesPorCuenta = $cuentas->mapWithKeys(fn (User $cuenta): array => [
            $cuenta->id => $cuenta->roles->pluck('rol')->sort(SORT_STRING)->values()->all(),
        ]);
        $roles = $rolesPorCuenta->collapse()->uniqueStrict()->sort(SORT_STRING)->values()->all();

        return new self(
            $roles,
            $rolesPorCuenta->contains(fn (array $rolesCuenta): bool => $rolesCuenta !== $roles),
            $rolesPorCuenta->all(),
        );
    }
}
