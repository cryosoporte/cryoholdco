<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class EventoLiquidacion extends Model
{
    public $timestamps = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'com_liq_evento');
    }

    public static function registrar(int $id, string $accion, User $usuario, CarbonImmutable $fecha, ?string $comentario = null): void
    {
        self::query()->insert([
            'liq_id' => $id, 'accion' => $accion, 'user_id' => $usuario->getKey(),
            'nombre' => $usuario->name, 'empresa' => $usuario->empresa,
            'comentario' => $comentario, 'fecha' => $fecha->toDateTimeString(),
        ]);
    }
}
