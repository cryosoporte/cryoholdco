<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvioLiquidacion extends Model
{
    public const PENDIENTE = 'P';

    public const ENVIADO = 'E';

    public const FALLIDO = 'F';

    public $timestamps = false;

    public $incrementing = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'com_liq_envio');
    }
}
