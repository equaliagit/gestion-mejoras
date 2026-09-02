<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Los botones de la ficha.
 *
 * Cada método hace lo mismo: comprueba el permiso, valida lo que ha llegado
 * del formulario y se lo pasa a ProposalWorkflow. Ninguno toca el estado por
 * su cuenta ni escribe en el historial: de eso se encarga el servicio.
 *
 * Si una transición no es válida, el servicio lanza InvalidTransition. Aquí
 * la convertimos en un aviso rojo en pantalla en lugar de una página de error:
 * puede pasar si dos personas del comité trabajan sobre la misma propuesta a
 * la vez y una se adelanta.
 */
class ProposalActionController extends Controller
{
    public function __construct(private readonly ProposalWorkflow $flujo) {}

    public function assignToMe(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('review', $proposal);

        return $this->intentar($proposal, fn () => $this->flujo->assignReviewer(
            $proposal,
            $request->user(),
            $request->user(),
        ), 'Te has asignado la propuesta. Ahora aparece a tu nombre.');
    }

    public function requestInfo(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('review', $proposal);

        $datos = $request->validate(
            ['pregunta' => ['required', 'string', 'min:10', 'max:2000']],
            ['pregunta.required' => 'Escribe qué necesitas saber.', 'pregunta.min' => 'Concreta un poco más la pregunta.'],
        );

        return $this->intentar($proposal, fn () => $this->flujo->requestInfo(
            $proposal,
            $request->user(),
            $datos['pregunta'],
        ), 'Pregunta enviada. La propuesta queda esperando respuesta.');
    }

    public function sendToCommittee(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('review', $proposal);

        $datos = $request->validate(
            ['fecha' => ['required', 'date', 'after_or_equal:today']],
            ['fecha.required' => 'Indica la fecha de la sesión.', 'fecha.after_or_equal' => 'La sesión no puede ser en el pasado.'],
        );

        $sesion = CommitteeSession::firstOrCreate(['held_on' => $datos['fecha']]);

        return $this->intentar($proposal, fn () => $this->flujo->sendToCommittee(
            $proposal,
            $sesion,
            $request->user(),
        ), 'Añadida al orden del día del '.$sesion->held_on->format('d/m/Y').'.');
    }

    /** Aprobar, rechazar o aplazar: los tres salen del mismo formulario. */
    public function decide(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('decide', $proposal);

        $datos = $request->validate([
            'decision' => ['required', Rule::in(['aprobar', 'rechazar', 'aplazar'])],
            'motivo' => ['required_unless:decision,aprobar', 'nullable', 'string', 'min:10', 'max:2000'],
            'revisar_el' => ['required_if:decision,aplazar', 'nullable', 'date', 'after:today'],
        ], [
            'motivo.required_unless' => 'Rechazar y aplazar exigen explicar por qué.',
            'motivo.min' => 'Explícalo un poco más: esto es lo que se le comunica a quien propuso.',
            'revisar_el.required_if' => 'Indica cuándo se vuelve a mirar.',
            'revisar_el.after' => 'La fecha de revisión tiene que ser futura.',
        ]);

        return match ($datos['decision']) {
            'aprobar' => $this->intentar($proposal, fn () => $this->flujo->approve(
                $proposal, $request->user(), $datos['motivo'] ?? null,
            ), 'Propuesta aprobada. Ahora toca planificar la implantación.'),

            'rechazar' => $this->intentar($proposal, fn () => $this->flujo->reject(
                $proposal, $request->user(), $datos['motivo'],
            ), 'Propuesta rechazada. Se ha guardado el motivo.'),

            'aplazar' => $this->intentar($proposal, fn () => $this->flujo->postpone(
                $proposal, $request->user(), $datos['motivo'], now()->parse($datos['revisar_el']),
            ), 'Propuesta aplazada. Se avisará al comité cuando llegue la fecha.'),
        };
    }

    public function plan(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('implement', $proposal);

        $datos = $request->validate([
            'responsable' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
        ], [
            'responsable.required' => 'Elige quién se encarga de implantarla.',
            'fin.after_or_equal' => 'El fin previsto no puede ser anterior al inicio.',
        ]);

        return $this->intentar($proposal, fn () => $this->flujo->planImplementation(
            $proposal,
            User::findOrFail($datos['responsable']),
            now()->parse($datos['inicio']),
            now()->parse($datos['fin']),
        ), 'Plan guardado. Se avisará si se pasa la fecha de fin.');
    }

    public function markImplemented(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('implement', $proposal);

        $datos = $request->validate([
            'resultado' => ['required', 'string', 'min:10', 'max:2000'],
            'cerrada_el' => ['nullable', 'date'],
        ], [
            'resultado.required' => 'Cuenta qué se ha conseguido: es lo que se comunica al cerrar.',
            'resultado.min' => 'Un poco más de detalle, que esto lo lee quien la propuso.',
        ]);

        return $this->intentar($proposal, fn () => $this->flujo->markImplemented(
            $proposal,
            $request->user(),
            $datos['resultado'],
            isset($datos['cerrada_el']) ? now()->parse($datos['cerrada_el']) : null,
        ), 'Propuesta cerrada como implantada. Enhorabuena.');
    }

    public function reopen(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->authorize('review', $proposal);

        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:2000']],
            ['motivo.required' => 'Explica por qué se reabre.'],
        );

        return $this->intentar($proposal, fn () => $this->flujo->reopen(
            $proposal,
            $request->user(),
            $datos['motivo'],
        ), 'Propuesta reabierta y de vuelta en revisión.');
    }

    /**
     * Ejecuta la acción y traduce el resultado a un aviso en pantalla.
     */
    private function intentar(Proposal $proposal, callable $accion, string $exito): RedirectResponse
    {
        try {
            $accion();
        } catch (InvalidTransition $e) {
            return back()->with('error', $e->getMessage().' Puede que alguien la haya movido mientras tanto: recarga la ficha.');
        }

        return redirect()
            ->route('proposals.show', $proposal)
            ->with('exito', $exito);
    }
}
