<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProposalActionController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ReportController;
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
    Route::get('/propuestas/empresa', [ProposalController::class, 'shared'])->name('proposals.shared');
    Route::get('/propuestas/nueva', [ProposalController::class, 'create'])->name('proposals.create');

    Route::get('/avisos', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/avisos/leidos', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::post('/propuestas', [ProposalController::class, 'store'])->name('proposals.store');

    // Los informes y la bandeja: cada uno con su permiso. El middleware
    // corta el paso antes incluso de entrar en el controlador.
    Route::middleware('can:reports.view')->group(function () {
        Route::get('/informes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/informes/descargar', [ReportController::class, 'export'])->name('reports.export');
    });

    // La bandeja del comité. El middleware corta el paso a quien no tenga
    // el permiso, antes incluso de entrar en el controlador.
    Route::get('/comite', [CommitteeController::class, 'inbox'])
        ->middleware('can:proposals.review')
        ->name('committee.inbox');

    // La ficha. {proposal} se convierte solo en el objeto Proposal.
    Route::get('/propuestas/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');

    Route::post('/propuestas/{proposal}/comentarios', [CommentController::class, 'store'])->name('comments.store');

    // Las acciones del flujo. Cada una es un formulario que llega por POST,
    // y cada una comprueba su permiso en el controlador.
    Route::prefix('/propuestas/{proposal}')->name('proposals.')->group(function () {
        Route::post('/asignarme', [ProposalActionController::class, 'assignToMe'])->name('assign');
        Route::post('/pedir-info', [ProposalActionController::class, 'requestInfo'])->name('requestInfo');
        Route::post('/al-comite', [ProposalActionController::class, 'sendToCommittee'])->name('toCommittee');
        Route::post('/decidir', [ProposalActionController::class, 'decide'])->name('decide');
        Route::post('/planificar', [ProposalActionController::class, 'plan'])->name('plan');
        Route::post('/implantada', [ProposalActionController::class, 'markImplemented'])->name('implemented');
        Route::post('/reabrir', [ProposalActionController::class, 'reopen'])->name('reopen');
    });
});
