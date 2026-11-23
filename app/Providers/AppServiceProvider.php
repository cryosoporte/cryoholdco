<?php

namespace App\Providers;

use App\Administracion\PermisosAdministracion;
use App\Models\User;
use App\Reportes\Comisiones\PermisosComisiones;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (PermisosAdministracion::ROLES_POR_ACCION as $accion => $roles) {
            Gate::define("administracion.$accion", fn (User $usuario): bool => $usuario->tieneRol(...$roles));
        }

        foreach (PermisosComisiones::ROLES_POR_ACCION as $accion => $roles) {
            Gate::define("comisiones.$accion", fn (User $usuario): bool => $usuario->tieneRol(...$roles));
        }
    }
}
