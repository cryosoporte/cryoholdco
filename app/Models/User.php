<?php

namespace App\Models;

use App\Administracion\RolSistema;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

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
