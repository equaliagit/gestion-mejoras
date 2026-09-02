<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Services\ProposalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Los comentarios de la ficha, que son de dos clases:
 *
 *   - los del hilo, que ve quien propuso
 *   - los de evaluación, marcados como internos, que solo ve el comité
 *
 * Además hay un automatismo: si la propuesta estaba esperando información y
 * quien la escribió contesta, vuelve sola a revisión. Nadie tiene que
 * acordarse de mover nada.
 */
class CommentController extends Controller
{
    public function __construct(private readonly ProposalWorkflow $flujo) {}

    public function store(Request $request, Proposal $proposal): RedirectResponse
    {
        $interno = $request->boolean('interno');

        $this->authorize($interno ? 'commentInternally' : 'comment', $proposal);

        $datos = $request->validate(
            ['cuerpo' => ['required', 'string', 'min:2', 'max:5000']],
            ['cuerpo.required' => 'Escribe algo antes de enviar.'],
        );

        Comment::create([
            'proposal_id' => $proposal->id,
            'user_id' => $request->user()->id,
            'body' => $datos['cuerpo'],
            'is_internal' => $interno,
        ]);

        $aviso = $interno
            ? 'Comentario de evaluación guardado. Solo lo ve el comité.'
            : 'Comentario publicado en el hilo.';

        // Contestar a una petición de información devuelve la propuesta a revisión.
        if (! $interno
            && $proposal->user_id === $request->user()->id
            && $proposal->status->hasCode(ProposalStatus::AWAITING_INFO)) {
            $this->flujo->infoProvided($proposal, $request->user(), $datos['cuerpo']);
            $aviso = 'Respuesta enviada. Tu propuesta vuelve a estar en revisión.';
        }

        return redirect()
            ->route('proposals.show', $proposal)
            ->with('exito', $aviso);
    }
}
