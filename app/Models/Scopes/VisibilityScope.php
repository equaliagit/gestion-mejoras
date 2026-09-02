<?php

namespace App\Models\Scopes;

use App\Enums\Visibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * El único sitio donde se decide qué propuestas puede ver alguien.
 *
 * Se aplica sola a TODAS las consultas de Proposal, así que una pantalla nueva
 * no puede olvidarse de comprobarlo: aunque su código no diga nada, el filtro
 * ya está puesto. Para saltárselo hay que pedirlo a gritos:
 *
 *     Proposal::withoutGlobalScope(VisibilityScope::class)
 *
 * que es lo que hacen los informes y las tareas programadas, que no se
 * ejecutan en nombre de ninguna persona.
 */
class VisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            // Tareas programadas y comandos: sin persona detrás, sin filtro.
            // En una petición web sin sesión no se ve absolutamente nada.
            if (! app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $builder->where(function (Builder $query) use ($user) {
            // Lo propio siempre, borradores incluidos.
            $query->where('user_id', $user->id);

            if ($user->canSeeRestrictedProposals()) {
                // El comité ve todo lo que se haya llegado a enviar.
                $query->orWhereNotNull('submitted_at');

                return;
            }

            // El resto de la plantilla, solo las públicas ya enviadas.
            $query->orWhere(function (Builder $sub) {
                $sub->whereNotNull('submitted_at')
                    ->where('visibility', Visibility::Public);
            });
        });
    }
}
