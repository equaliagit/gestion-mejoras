<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
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

    /**
     * Abrir un aviso: se marca como leído y te lleva a la propuesta.
     *
     * El enlace pasa por aquí en vez de ir directo a la ficha para que leerlo
     * cuente como leerlo. Si la propuesta ya no existe o no puedes verla,
     * el aviso se marca igual y vuelves al buzón: nada de callejones sin salida.
     */
    public function open(Request $request, string $aviso): RedirectResponse
    {
        $notificacion = $request->user()->notifications()->whereKey($aviso)->firstOrFail();
        $notificacion->markAsRead();

        $propuesta = Proposal::query()->find($notificacion->data['proposal_id'] ?? null);

        if (! $propuesta || $request->user()->cannot('view', $propuesta)) {
            return redirect()
                ->route('notifications.index')
                ->with('error', 'Esa propuesta ya no está disponible para ti.');
        }

        return redirect()->route('proposals.show', $propuesta);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('exito', 'Todos los avisos marcados como leídos.');
    }
}
