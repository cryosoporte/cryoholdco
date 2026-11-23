<?php

namespace App\Models;

use App\Administracion\RolSistema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

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

    public function tieneRol(RolSistema ...$roles): bool
    {
        return $this->roles->contains(fn (UsuarioRol $asignacion): bool => in_array($asignacion->rol, $roles, true));
    }
}
