<?php

use App\Http\Controllers\Administracion\AccesoLaboratorioController;
use App\Http\Controllers\Administracion\HistorialSeguridadController;
use App\Http\Controllers\Administracion\RolController;
use App\Http\Controllers\Administracion\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LoginLaboratorioController;
use App\Http\Controllers\Reportes\ComisionesController;
use App\Http\Controllers\Reportes\FuenteController;
use App\Http\Controllers\Reportes\LiquidacionesComisionesController;
use App\Http\Controllers\Serologia\ClienteController;
use App\Http\Controllers\Serologia\LaboratorioController;
use App\Http\Controllers\Serologia\SerologiaController;
use App\Http\Middleware\LaboratorioConAcceso;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::prefix('serologia')->group(function () {
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'consultar'])->name('clientes.consultar');

    Route::middleware(['auth:laboratorio', LaboratorioConAcceso::class])->group(function () {
        Route::get('/laboratorio', [LaboratorioController::class, 'index'])->name('laboratorio.index');
        Route::post('/laboratorio', [LaboratorioController::class, 'consultar'])->name('laboratorio.consultar');
        Route::post('/laboratorio/marcar', [LaboratorioController::class, 'marcar'])->name('laboratorio.marcar');
        Route::post('/laboratorio/sede', [LaboratorioController::class, 'sede'])->name('laboratorio.sede');
    });

    Route::middleware('guest:laboratorio')->group(function () {
        Route::get('/laboratorio/login', [LoginLaboratorioController::class, 'index'])->name('laboratorio.login');
        Route::post('/laboratorio/login', [LoginLaboratorioController::class, 'store'])->name('laboratorio.login.store');
    });
    Route::post('/laboratorio/logout', [LoginLaboratorioController::class, 'destroy'])
        ->middleware('auth:laboratorio')->name('laboratorio.logout');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::inertia('/', 'dashboard')->name('home');
    Route::inertia('/operaciones', 'operaciones')->name('operaciones');

    // serología
    Route::get('/serologia', [SerologiaController::class, 'index'])->name('serologia');
    Route::post('/serologia/recordatorio', [SerologiaController::class, 'enviarRecordatorio'])->name('serologia.recordatorio');

    // administración
    $autorizarModeloFaltante = fn (string $permiso): Closure => function (Request $request, ModelNotFoundException $exception) use ($permiso): never {
        Gate::authorize($permiso);

        throw $exception;
    };

    Route::inertia('/administracion', 'administracion')->name('administracion')->middleware('can:administracion.ver');
    Route::middleware('can:administracion.laboratorios')->group(function () use ($autorizarModeloFaltante) {
        Route::get('/administracion/laboratorios', [AccesoLaboratorioController::class, 'index'])->name('administracion.laboratorios');
        Route::post('/administracion/laboratorios', [AccesoLaboratorioController::class, 'crear'])->name('administracion.laboratorios.crear');
        Route::put('/administracion/laboratorios/{acceso}', [AccesoLaboratorioController::class, 'actualizar'])
            ->name('administracion.laboratorios.actualizar')
            ->missing($autorizarModeloFaltante('administracion.laboratorios'));
    });
    Route::middleware('can:administracion.usuarios')->group(function () use ($autorizarModeloFaltante) {
        Route::get('/administracion/usuarios', [UsuarioController::class, 'index'])->name('administracion.usuarios');
        Route::put('/administracion/usuarios/{usuario}', [UsuarioController::class, 'asignarRoles'])
            ->name('administracion.usuarios.roles')
            ->whereNumber('usuario')
            ->block(10, 10)
            ->missing($autorizarModeloFaltante('administracion.usuarios'));
    });

    Route::middleware('can:administracion.roles')->prefix('/administracion/roles')->controller(RolController::class)->group(function () use ($autorizarModeloFaltante) {
        Route::get('/', 'index')->name('administracion.roles');
        Route::post('/', 'crear')->name('administracion.roles.crear')->block(10, 10);

        $rolFaltante = $autorizarModeloFaltante('administracion.roles');
        Route::prefix('/{rol}')->where(['rol' => '[A-Z0-9_]+'])->name('administracion.roles.')->group(function () use ($rolFaltante) {
            Route::get('/', 'mostrar')->name('mostrar')->missing($rolFaltante);
            Route::put('/', 'actualizar')->name('actualizar')->block(10, 10)->missing($rolFaltante);
            Route::put('/permisos', 'permisos')->name('permisos')->block(10, 10)->missing($rolFaltante);
            Route::delete('/', 'eliminar')->name('eliminar')->block(10, 10)->missing($rolFaltante);
        });
    });

    Route::get('/administracion/historial', [HistorialSeguridadController::class, 'index'])
        ->name('administracion.historial')
        ->middleware('can:administracion.historial');

    // reportes
    Route::inertia('/reportes', 'reportes')->name('reportes');
    Route::get('/reportes/fuentes', [FuenteController::class, 'index'])->name('reportes.fuentes');
    Route::post('/reportes/fuentes/{fuente}/cargar', [FuenteController::class, 'cargar'])
        ->where('fuente', '[a-z_]+')
        ->name('reportes.fuentes.cargar');
    Route::middleware('can:comisiones.ver')->prefix('/reportes/comisiones')->group(function () {
        Route::get('/', [ComisionesController::class, 'index'])
            ->name('reportes.comisiones');
        Route::controller(LiquidacionesComisionesController::class)->prefix('/liquidaciones')->name('reportes.comisiones.liquidaciones.')->group(function () {
            Route::post('/', 'nueva')->name('nueva')->middleware('can:comisiones.procesar')->block(300, 30);

            Route::prefix('/{liquidacion}')->where(['liquidacion' => '[0-9]{4}-[0-9]{2}|[0-9]{4}-T[1-4]'])->group(function () {
                Route::get('/', 'mostrar')->name('mostrar');
                Route::post('/procesar', 'procesar')->name('procesar')->middleware('can:comisiones.procesar')->block(300, 30);
                Route::post('/aprobar', 'aprobar')->name('aprobar')->middleware('can:comisiones.aprobar')->block(300, 30);
                Route::post('/rechazar', 'rechazar')->name('rechazar')->middleware('can:comisiones.rechazar')->block(300, 30);
                Route::post('/enviar', 'enviar')->name('enviar')->middleware('can:comisiones.enviar')->block(300, 30);
                Route::post('/reenviar', 'reenviar')->name('reenviar')->middleware('can:comisiones.enviar')->block(300, 30);
                Route::get('/correos/gerencia', 'correoGerencia')->name('correo-gerencia');
                Route::get('/correos/asesor/{asesor}', 'correoAsesor')->name('correo-asesor');
            });
        });
    });
});
