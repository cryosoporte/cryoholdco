<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Serologia\ClienteController;
use App\Http\Controllers\Serologia\LaboratorioController;
use App\Http\Controllers\Serologia\SerologiaController;
use Illuminate\Support\Facades\Route;

Route::prefix('serologia')->group(function () {
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'consultar'])->name('clientes.consultar');

    Route::get('/laboratorio', [LaboratorioController::class, 'index'])->name('laboratorio.index');
    Route::post('/laboratorio', [LaboratorioController::class, 'consultar'])->name('laboratorio.consultar');
    Route::post('/laboratorio/marcar', [LaboratorioController::class, 'marcar'])->name('laboratorio.marcar');
    Route::post('/laboratorio/sede', [LaboratorioController::class, 'sede'])->name('laboratorio.sede');
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
