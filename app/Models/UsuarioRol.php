<?php

namespace App\Models;

use App\Administracion\RolSistema;
use Illuminate\Database\Eloquent\Model;

class UsuarioRol extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'usuario_rol');
    }

    /** @return array{rol: class-string<RolSistema>} */
    protected function casts(): array
    {
        return ['rol' => RolSistema::class];
    }
}
