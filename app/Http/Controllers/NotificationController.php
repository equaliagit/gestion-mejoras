<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El buzón interno: lo mismo que llega por correo, guardado dentro de la
 * aplicación para quien no mira el correo o lo tiene enterrado.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'avisos' => $request->user()->notifications()->latest()->limit(60)->get(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('exito', 'Todos los avisos marcados como leídos.');
    }
}
