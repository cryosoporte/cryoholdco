<?php

namespace App\Reportes\Comisiones;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Colaboradores que comisionan, leídos de USERS. CODIGO_NETSUITE es el nombre
 * con el que la persona figura en las fuentes de NetSuite y ROL_NETSUITE el
 * rol de COM_META con el que comisiona. Solo consulta.
 */
class AsesoresComisiones
{
    /**
     * @return list<AsesorComision> ordenados por rol y nombre
     */
    public function todos(): array
    {
        return DB::table('users')
            ->whereNotNull('codigo_netsuite')
            ->whereNotNull('rol_netsuite')
            ->orderBy('rol_netsuite')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'codigo_netsuite', 'rol_netsuite'])
            ->map(fn (object $usuario): AsesorComision => new AsesorComision(
                id: (int) $usuario->id,
                nombre: $usuario->name,
                correo: $usuario->email,
                rol: $usuario->rol_netsuite,
                codigoNetsuite: $usuario->codigo_netsuite,
            ))
            ->all();
    }

    /**
     * Clave para comparar un nombre de NetSuite con CODIGO_NETSUITE: sin
     * espacios repetidos ni en los extremos, en mayúsculas.
     * "Vasquez  Monteza, Marcos" y "VASQUEZ MONTEZA, MARCOS" dan lo mismo.
     */
    public static function normalizar(string $nombre): string
    {
        return Str::upper(Str::squish($nombre));
    }
}
