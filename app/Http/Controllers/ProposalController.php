<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\Visibility;
use App\Http\Requests\StoreProposalRequest;
use App\Models\Area;
use App\Models\Impact;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\User;
use App\Services\ProposalWorkflow;
use App\Support\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Las pantallas del proponente.
 *
 * El controlador es deliberadamente corto: recoge lo que llega, se lo pide al
 * servicio y devuelve una vista. Ni valida a mano (de eso va StoreProposalRequest)
 * ni decide quién ve qué (de eso van VisibilityScope y ProposalPolicy).
 */
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalWorkflow $flujo) {}

    /** Mis propuestas. El filtro de visibilidad ya está puesto por debajo. */
    public function index(Request $request): View
    {
        $propuestas = Proposal::query()
            ->where('user_id', $request->user()->id)
            ->with(['area', 'status', 'reviewer'])
            ->latest('updated_at')
            ->get();

        return view('proposals.index', [
            'propuestas' => $propuestas,
            'resumen' => [
                'total' => $propuestas->count(),
                'abiertas' => $propuestas->filter(
                    fn (Proposal $p) => ! $p->isDraft() && $p->status->is_open
                )->count(),
                'implantadas' => $propuestas->filter(
                    fn (Proposal $p) => $p->status->hasCode(ProposalStatus::IMPLEMENTED)
                )->count(),
                'borradores' => $propuestas->filter(fn (Proposal $p) => $p->isDraft())->count(),
            ],
        ]);
    }

    /**
     * Las propuestas públicas de toda la empresa.
     *
     * Se pide explícitamente solo las públicas: el filtro de visibilidad
     * dejaría pasar también las privadas propias, y aquí no pintan nada.
     */
    public function shared(Request $request): View
    {
        return view('proposals.shared', [
            'propuestas' => Proposal::query()
                ->submitted()
                ->where('visibility', Visibility::Public)
                ->with(['area', 'status', 'author'])
                ->latest('submitted_at')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Proposal::class);

        return view('proposals.create', [
            'areas' => Area::active()->get(),
            'impactos' => Impact::active()->get(),
            'prioridades' => Priority::cases(),
            'visibilidades' => Visibility::cases(),
        ]);
    }

    /**
     * La ficha completa. La propuesta llega ya resuelta por la URL, y si el
     * filtro de visibilidad no la deja pasar, Laravel devuelve un 404 antes
     * de llegar aquí: quien no puede verla ni siquiera sabe que existe.
     */
    public function show(Request $request, Proposal $proposal): View
    {
        $this->authorize('view', $proposal);

        $proposal->load([
            'area', 'status', 'author', 'reviewer', 'implementer', 'impacts', 'committeeSession',
            'statusChanges.toStatus', 'statusChanges.fromStatus', 'statusChanges.user',
            'comments.user',
        ]);

        return view('proposals.show', [
            'propuesta' => $proposal,
            'comentarios' => $proposal->comments->filter(
                fn ($c) => ! $c->is_internal || $request->user()->canSeeRestrictedProposals()
            ),
            'siguientes' => Workflow::nextFrom($proposal->status->code),
            // Para el desplegable de «responsable de implantación».
            'responsables' => $request->user()->can('implement', $proposal)
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(StoreProposalRequest $request): RedirectResponse
    {
        $propuesta = $this->flujo->startDraft($request->user(), $request->datosDePropuesta());
        $propuesta->impacts()->sync($request->safe()->impacts);

        if (! $request->quiereEnviar()) {
            return redirect()
                ->route('proposals.index')
                ->with('exito', 'Guardada como borrador. Solo la ves tú hasta que la envíes.');
        }

        $propuesta = $this->flujo->submit($propuesta, $request->user());

        return redirect()
            ->route('proposals.index')
            ->with('exito', "Propuesta enviada con la referencia {$propuesta->reference}. Te avisaremos en cuanto haya novedades.");
    }
}
