<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioRol extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'usuario_rol');
    }

    /** @return BelongsTo<Rol, $this> */
    public function definicionRol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol', 'codigo');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Serializa los cambios de asignaciones y la eliminación de roles en Oracle dentro de una transacción. */
    public function bloquearAsignaciones(): void
    {
        $conexion = $this->getConnection();

        if ($conexion->getDriverName() === 'oracle') {
            $tabla = $conexion->getQueryGrammar()->wrapTable($this->getTable());
            $conexion->statement("LOCK TABLE {$tabla} IN EXCLUSIVE MODE");
        }
    }
}
