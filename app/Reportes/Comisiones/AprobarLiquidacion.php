<?php

namespace App\Reportes\Comisiones;

use App\Models\EventoLiquidacion;
use App\Models\FuenteCarga;
use App\Models\LiquidacionComision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AprobarLiquidacion
{
    public function ejecutar(int $id, User $usuario): void
    {
        Gate::forUser($usuario)->authorize('comisiones.aprobar');
        DB::transaction(function () use ($id, $usuario): void {
            $liquidacion = LiquidacionComision::bloquearEnEstado($id, LiquidacionComision::PROCESADA);
            $liquidacion->estado = LiquidacionComision::APROBADA;
            $liquidacion->save();
            EventoLiquidacion::registrar($id, 'APROBACION', $usuario, FuenteCarga::ahora());
        });
    }
}
