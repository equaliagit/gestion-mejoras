<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Clase base de todos los controladores.
 *
 * AuthorizesRequests es lo que hace que dentro de un controlador se pueda
 * escribir $this->authorize('create', Proposal::class): consulta la política
 * y, si dice que no, corta la petición con un 403 sin más ceremonia.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
