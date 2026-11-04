<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Tabla ICTC.USERS, compartida con el sistema anterior (una fila por usuario y compañía).
 * Este sistema solo la lee.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];
}
