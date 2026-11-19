<?php

namespace App\Providers;

use App\Reportes\Comisiones\DatosComisiones;
use App\Reportes\Comisiones\DatosEjemploComisiones;
use App\Reportes\Comisiones\DatosRealesComisiones;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(DatosComisiones::class, fn (Application $app): DatosComisiones => config('reportes.comisiones_demo.datos') === 'ejemplo'
            ? $app->make(DatosEjemploComisiones::class)
            : $app->make(DatosRealesComisiones::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
