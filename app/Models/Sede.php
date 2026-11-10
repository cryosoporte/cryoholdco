<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla ICTC.LAB_LABORATORIOS_SEDES: las sedes donde se toma la muestra.
 *
 * El nombre comercial del laboratorio no está aquí sino en CXP_PROVEEDORES
 * (el laboratorio es un proveedor), por eso los dos métodos hacen ese join.
 *
 * Mientras no exista el inicio de sesión por empresa, el laboratorio y la
 * sede se eligen a mano en la pantalla y viven en la sesión.
 */
class Sede extends Model
{
    protected $table = 'lab_laboratorios_sedes';
    public $timestamps = false;

    public const ESTADO_ACTIVA = 'A';

    /** Los laboratorios que tienen al menos una sede activa: RUC y nombre. */
    public static function laboratorios()
    {
        return static::query()
            ->from('lab_laboratorios_sedes as l')
            ->join('cxp_proveedores as p', function ($join) {
                $join->on('p.no_cia', '=', 'l.no_cia')
                    ->on('p.identificacion', '=', 'l.identificacion');
            })
            ->select('l.identificacion', 'p.alias')
            ->where('l.estado', self::ESTADO_ACTIVA)
            ->distinct()
            ->orderBy('p.alias')
            ->get();
    }

    /** Las sedes activas de un laboratorio. */
    public static function deLaboratorio(string $laboratorio)
    {
        return static::query()
            ->select('codigo_sede', 'descripcion_sede', 'direccion_sede', 'ciudad')
            ->where('identificacion', $laboratorio)
            ->where('estado', self::ESTADO_ACTIVA)
            ->orderBy('codigo_sede')
            ->get();
    }

    /** El nombre comercial del laboratorio: "SUIZA LAB". */
    public static function alias(string $laboratorio): ?string
    {
        return static::query()
            ->from('cxp_proveedores')
            ->where('identificacion', $laboratorio)
            ->value('alias');
    }

    /** El nombre de una sede, para los títulos de la pantalla. */
    public static function nombre(string $laboratorio, string $sede): ?string
    {
        return static::query()
            ->where('identificacion', $laboratorio)
            ->where('codigo_sede', $sede)
            ->value('descripcion_sede');
    }
}
