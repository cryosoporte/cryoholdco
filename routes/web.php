<?php

use App\Http\Controllers\Administracion\AccesoLaboratorioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LoginLaboratorioController;
use App\Http\Controllers\Reportes\ComisionesController;
use App\Http\Controllers\Reportes\FuenteController;
use App\Http\Controllers\Serologia\ClienteController;
use App\Http\Controllers\Serologia\LaboratorioController;
use App\Http\Controllers\Serologia\SerologiaController;
use App\Http\Middleware\LaboratorioConAcceso;
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
    Route::inertia('/administracion', 'administracion')->name('administracion');
    Route::get('/administracion/laboratorios', [AccesoLaboratorioController::class, 'index'])->name('administracion.laboratorios');
    Route::post('/administracion/laboratorios', [AccesoLaboratorioController::class, 'crear'])->name('administracion.laboratorios.crear');
    Route::put('/administracion/laboratorios/{acceso}', [AccesoLaboratorioController::class, 'actualizar'])->name('administracion.laboratorios.actualizar');

    // reportes
    Route::inertia('/reportes', 'reportes')->name('reportes');
    Route::get('/reportes/fuentes', [FuenteController::class, 'index'])->name('reportes.fuentes');
    Route::post('/reportes/fuentes/{fuente}/cargar', [FuenteController::class, 'cargar'])
        ->where('fuente', '[a-z_]+')
        ->name('reportes.fuentes.cargar');
    Route::get('/reportes/comisiones', [ComisionesController::class, 'index'])->name('reportes.comisiones');
});
