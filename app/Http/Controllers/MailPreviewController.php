<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Models\StatusChange;
use App\Notifications\ProposalUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ver los correos en el navegador, tal cual le llegarán a la gente.
 *
 * Existe SOLO en el entorno local, para poder revisar los textos y el diseño
 * sin tener que mandar correos de verdad ni usar direcciones reales. En el
 * servidor esta ruta ni siquiera está registrada.
 */
class MailPreviewController extends Controller
{
    /** Lista los cambios de estado que hay, para elegir cuál previsualizar. */
    public function index(): Response
    {
        $cambios = StatusChange::query()
            ->with(['toStatus', 'proposal'])
            ->latest('id')
            ->limit(40)
            ->get();

        $filas = $cambios->map(fn (StatusChange $c) => sprintf(
            '<li style="margin:6px 0"><a href="%s">%s → <strong>%s</strong></a> <span style="color:#777">· %s</span></li>',
            route('dev.mail.show', $c),
            e($c->proposal?->reference ?? 'sin referencia'),
            e($c->toStatus->name),
            e($c->created_at->format('d/m/Y H:i')),
        ))->implode('');

        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            .'<title>Correos de prueba</title></head>'
            .'<body style="font-family:system-ui,sans-serif; max-width:640px; margin:40px auto; padding:0 20px">'
            .'<h1 style="font-size:20px">Correos que se enviarían</h1>'
            .'<p style="color:#555">Cada línea es un cambio de estado ya ocurrido. Pulsa para ver el correo '
            .'que recibió quien propuso, tal cual le llega.</p>'
            ."<ul style=\"padding-left:18px\">{$filas}</ul>"
            .'</body></html>'
        );
    }

    /** Pinta el correo de un cambio de estado concreto. */
    public function show(Request $request, StatusChange $cambio): Response
    {
        $propuesta = Proposal::withoutGlobalScopes()->find($cambio->proposal_id);

        if (! $propuesta || ! $propuesta->author) {
            throw new NotFoundHttpException('Esa propuesta ya no existe.');
        }

        $publico = $request->query('publico', ProposalUpdate::PARA_AUTOR);

        $aviso = new ProposalUpdate($propuesta, $cambio, $publico);

        return response($aviso->toMail($propuesta->author)->render());
    }
}
