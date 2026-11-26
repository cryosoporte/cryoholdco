<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Serologia\ClienteController;
use App\Http\Controllers\Serologia\LaboratorioController;
use App\Http\Controllers\Serologia\SerologiaController;
<<<<<<< Updated upstream
=======
use App\Http\Middleware\LaboratorioConAcceso;
use App\Http\Middleware\SedeLaboratorioElegida;
>>>>>>> Stashed changes
use Illuminate\Support\Facades\Route;

Route::prefix('serologia')->group(function () {
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'consultar'])->name('clientes.consultar');

<<<<<<< Updated upstream
    Route::get('/laboratorio', [LaboratorioController::class, 'index'])->name('laboratorio.index');
    Route::post('/laboratorio', [LaboratorioController::class, 'consultar'])->name('laboratorio.consultar');
    Route::post('/laboratorio/marcar', [LaboratorioController::class, 'marcar'])->name('laboratorio.marcar');
    Route::post('/laboratorio/sede', [LaboratorioController::class, 'sede'])->name('laboratorio.sede');
=======
    Route::middleware(['auth:laboratorio', LaboratorioConAcceso::class])->group(function () {
        Route::get('/laboratorio/sede', [LaboratorioController::class, 'elegirSede'])->name('laboratorio.sede.elegir');
        Route::post('/laboratorio/sede', [LaboratorioController::class, 'sede'])->name('laboratorio.sede');

        Route::middleware(SedeLaboratorioElegida::class)->group(function () {
            Route::get('/laboratorio', [LaboratorioController::class, 'index'])->name('laboratorio.index');
            Route::post('/laboratorio', [LaboratorioController::class, 'consultar'])->name('laboratorio.consultar');
            Route::post('/laboratorio/marcar', [LaboratorioController::class, 'marcar'])->name('laboratorio.marcar');
        });
    });

    Route::middleware('guest:laboratorio')->group(function () {
        Route::get('/laboratorio/login', [LoginLaboratorioController::class, 'index'])->name('laboratorio.login');
        Route::post('/laboratorio/login', [LoginLaboratorioController::class, 'store'])->name('laboratorio.login.store');
    });
    Route::post('/laboratorio/logout', [LoginLaboratorioController::class, 'destroy'])
        ->middleware('auth:laboratorio')->name('laboratorio.logout');
>>>>>>> Stashed changes
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
});
