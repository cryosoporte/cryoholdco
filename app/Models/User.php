<?php

namespace App\Models;

use App\Administracion\CatalogoPermisos;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

    /** @var list<string>|null */
    private ?array $permisosEfectivos = null;

    /** Identifica a la persona por DNI o por correo cuando no tiene DNI. */
    public function clavePersona(): string
    {
        $dni = trim((string) $this->identificacion);

        return $dni !== ''
            ? 'dni:'.$dni
            : 'correo:'.mb_strtolower(trim((string) $this->email));
    }

    /**
     * Aplica la misma normalización que el listado para encontrar las cuentas de la persona.
     *
     * @return Collection<int, User>
     */
    public function filasDeLaPersona(): Collection
    {
        $clave = $this->clavePersona();

        return static::query()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'identificacion', 'empresa'])
            ->filter(fn (User $cuenta): bool => $cuenta->clavePersona() === $clave)
            ->values();
    }

    /** @return HasMany<UsuarioRol, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(UsuarioRol::class, 'user_id');
    }

    /** @return list<string> */
    public function codigosPermiso(): array
    {
        if ($this->permisosEfectivos !== null) {
            return $this->permisosEfectivos;
        }

        $this->loadMissing('roles.definicionRol.permisos');
        $permisos = $this->roles->pluck('definicionRol')->filter()
            ->flatMap(fn (Rol $rol): array => $rol->permisosPermitidos($rol->permisos->pluck('permiso')->all()))->all();

        if ($this->roles->containsStrict('rol', Rol::ADMINISTRADOR)) {
            $permisos = [...$permisos, ...CatalogoPermisos::codigosAdministracion()];
        }

        return $this->permisosEfectivos = array_values(array_intersect(array_keys(CatalogoPermisos::PERMISOS), $permisos));
    }

    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->codigosPermiso(), true);
    }
}
