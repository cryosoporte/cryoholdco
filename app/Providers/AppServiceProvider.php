<?php

namespace App\Providers;

use App\Administracion\CatalogoPermisos;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (array_keys(CatalogoPermisos::PERMISOS) as $codigo) {
            Gate::define($codigo, fn (User $usuario): bool => $usuario->tienePermiso($codigo));
        }

        Gate::define('administracion.ver', fn (User $usuario): bool => array_intersect(
            $usuario->codigosPermiso(),
            CatalogoPermisos::codigosAdministracion(),
        ) !== []);
        Gate::define('administracion.historial', fn (User $usuario): bool => $usuario->tienePermiso('administracion.usuarios')
            || $usuario->tienePermiso('administracion.roles'));
    }
}
