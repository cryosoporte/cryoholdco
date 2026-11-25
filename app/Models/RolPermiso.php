<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolPermiso extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'rol_permiso');
    }
}
