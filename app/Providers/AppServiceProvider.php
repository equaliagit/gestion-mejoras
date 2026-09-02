<?php

namespace App\Providers;

use App\Listeners\SendProposalNotifications;
use App\Models\Proposal;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->compartirDatosDelMenu();

        // Ojo: NO hay que registrar aquí SendProposalNotifications.
        // Laravel encuentra solo los oyentes de app/Listeners mirando el tipo
        // del parámetro de su método handle(). Si además se registran a mano,
        // se enganchan dos veces y cada aviso sale por duplicado.
    }

    /**
     * El menú lateral necesita los mismos tres datos en todas las pantallas.
     *
     * Un «view composer» los calcula una vez y se los entrega a la plantilla
     * base, en lugar de que cada controlador se acuerde de pasarlos. Es el
     * equivalente a un @ModelAttribute global de Spring MVC.
     */
    private function compartirDatosDelMenu(): void
    {
        View::composer('layouts.app', function (ViewInstance $view) {
            $usuario = auth()->user();

            if (! $usuario) {
                return;
            }

            $view->with([
                'misPropuestas' => Proposal::query()->where('user_id', $usuario->id)->count(),
                'avisosSinLeer' => $usuario->unreadNotifications()->count(),
                'iniciales' => collect(explode(' ', $usuario->name))
                    ->filter()
                    ->take(2)
                    ->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
                    ->implode(''),
            ]);
        });
    }
}
