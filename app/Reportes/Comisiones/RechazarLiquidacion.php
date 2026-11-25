<?php

namespace App\Reportes\Comisiones;

use App\Models\EventoLiquidacion;
use App\Models\FuenteCarga;
use App\Models\LiquidacionComision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RechazarLiquidacion
{
    public const REGLAS_COMENTARIO = ['comentario' => ['required', 'string', 'min:10', 'max:500']];

    public const MENSAJES_COMENTARIO = [
        'comentario.required' => 'Escribe un comentario para devolver la liquidación.',
        'comentario.string' => 'Escribe el comentario como texto.',
        'comentario.min' => 'El comentario debe tener al menos 10 caracteres.',
        'comentario.max' => 'El comentario no puede superar los 500 caracteres.',
    ];

    public function __construct(private LiquidacionesComisiones $liquidaciones) {}

    public function ejecutar(int $id, User $usuario, string $comentario): void
    {
        Gate::forUser($usuario)->authorize('comisiones.rechazar');
        $comentario = trim($comentario);
        DB::transaction(function () use ($id, $usuario, $comentario): void {
            $liquidacion = LiquidacionComision::bloquearEnEstado($id, LiquidacionComision::PROCESADA);
            if ($this->liquidaciones->fueProcesadaPor($id, $usuario)) {
                throw ValidationException::withMessages(['accion' => 'No puedes rechazar una liquidación que procesaste.']);
            }

            Validator::make(compact('comentario'), self::REGLAS_COMENTARIO, self::MENSAJES_COMENTARIO)->validate();
            $liquidacion->estado = LiquidacionComision::RECHAZADA;
            $liquidacion->save();
            EventoLiquidacion::registrar($id, 'RECHAZO', $usuario, FuenteCarga::ahora(), $comentario);
        });
    }
}
