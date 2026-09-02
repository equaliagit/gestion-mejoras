<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Models\ProposalStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La bandeja del comité: todo lo que está vivo, ordenado por lo que lleva
 * más tiempo esperando. El filtro de visibilidad deja pasar aquí también las
 * privadas y las anónimas porque quien entra tiene el permiso para verlas;
 * lo que nunca aparece es quién firma una anónima.
 */
class CommitteeController extends Controller
{
    public function inbox(Request $request): View
    {
        $filtro = $request->query('estado');

        $propuestas = Proposal::query()
            ->submitted()
            ->when($filtro === 'mias', fn ($q) => $q->where('reviewer_id', $request->user()->id))
            ->when($filtro === 'sin-asignar', fn ($q) => $q->whereNull('reviewer_id'))
            ->when($filtro && ! in_array($filtro, ['mias', 'sin-asignar', 'cerradas'], true),
                fn ($q) => $q->inStatus($filtro))
            ->when($filtro !== 'cerradas' && ! $filtro, fn ($q) => $q->open())
            ->with(['area', 'status', 'author', 'reviewer'])
            ->orderBy('submitted_at')
            ->get();

        $abiertas = Proposal::query()->open()->get();

        return view('committee.inbox', [
            'propuestas' => $propuestas,
            'filtro' => $filtro,
            'contadores' => [
                'abiertas' => $abiertas->count(),
                'sin_asignar' => $abiertas->whereNull('reviewer_id')->count(),
                'mias' => $abiertas->where('reviewer_id', $request->user()->id)->count(),
                'en_comite' => $abiertas->filter(
                    fn (Proposal $p) => $p->status->hasCode(ProposalStatus::IN_COMMITTEE)
                )->count(),
            ],
            'misPropuestas' => Proposal::query()->where('user_id', $request->user()->id)->count(),
            'iniciales' => $this->iniciales($request->user()->name),
        ]);
    }

    private function iniciales(string $nombre): string
    {
        return collect(explode(' ', $nombre))
            ->filter()
            ->take(2)
            ->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->implode('');
    }
}
