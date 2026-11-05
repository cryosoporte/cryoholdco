<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SerologiaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        return Inertia::render('dashboard');
    })->name('home');

    // serología
    Route::get('/serologia', [SerologiaController::class, 'index'])->name('serologia');
});
