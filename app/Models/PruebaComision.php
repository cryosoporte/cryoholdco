<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla ICTC.PRUEBA_COMISIONES: tabla temporal para validar la carga del CSV de NetSuite.
 */
class PruebaComision extends Model
{
    protected $table = 'prueba_comisiones';

    protected $primaryKey = 'id_interno';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id_interno', 'numero_documento', 'fecha_emision', 'importe'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'importe' => 'decimal:2',
        ];
    }
}
