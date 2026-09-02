<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProposalController;
use Illuminate\Support\Facades\Route;

/*
| Las URL de la aplicación.
|
| Cada línea dice: para esta dirección y este verbo HTTP, llama a este método
| de este controlador. El ->name(...) es una etiqueta para poder escribir
| route('proposals.index') en las plantillas en vez de la dirección a pelo:
| si mañana cambia la URL, no hay que buscarla por todo el proyecto.
*/

Route::redirect('/', '/propuestas');

// Solo para quien NO ha entrado todavía.
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

// De aquí para abajo hace falta haber entrado.
Route::middleware('auth')->group(function () {
    Route::post('/salir', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/propuestas', [ProposalController::class, 'index'])->name('proposals.index');
    Route::get('/propuestas/nueva', [ProposalController::class, 'create'])->name('proposals.create');
    Route::post('/propuestas', [ProposalController::class, 'store'])->name('proposals.store');
});
