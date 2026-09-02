@extends('layouts.app')

@section('titulo', $propuesta->reference.' · Buzón de Mejora')

@section('contenido')
    @php
        $usuario = auth()->user();
        $esComite = $usuario->canSeeRestrictedProposals();
        $puedeRevisar = $usuario->can('review', $propuesta);
        $puedeDecidir = $usuario->can('decide', $propuesta);
        $puedeImplantar = $usuario->can('implement', $propuesta);
        $esAutor = $propuesta->user_id === $usuario->id;
    @endphp

    <p class="migas">
        <a href="{{ route('proposals.index') }}">Mis propuestas</a>
        @if ($esComite) · <a href="{{ route('committee.inbox') }}">Bandeja del comité</a> @endif
    </p>

    <div class="topbar">
        <div class="grow">
            <p class="ref">{{ $propuesta->reference ?? 'Borrador sin enviar' }}
                @if ($propuesta->submitted_at)
                    · Registrada el {{ $propuesta->submitted_at->translatedFormat('j \d\e F \d\e Y') }}
                @endif
            </p>
            <h1>{{ $propuesta->title }}</h1>
            <p class="sub">
                {{ $propuesta->authorName() }} · {{ $propuesta->area->name }} ·
                <span class="prio prio-{{ $propuesta->effectivePriority()->value }}">
                    {{ mb_strtoupper($propuesta->effectivePriority()->label()) }}
                </span>
                @if ($propuesta->visibility !== \App\Enums\Visibility::Public)
                    · <span class="tag tag-dashed">{{ $propuesta->visibility->label() }}</span>
                @endif
            </p>
        </div>
        <span class="pill pill-{{ $propuesta->status->color }}">{{ $propuesta->status->name }}</span>
    </div>

    <div class="detail">
        {{-- ------------------------------------------------ Columna izquierda --}}
        <div class="col">
            <div class="block">
                <div class="block-h">
                    <span>La propuesta</span>
                    <span class="tag">{{ $propuesta->visibility->label() }}</span>
                </div>
                <div class="block-b">
                    <h3>Problema actual</h3>
                    <p>{!! nl2br(e($propuesta->problem)) !!}</p>

                    <h3>Mejora propuesta</h3>
                    <p>{!! nl2br(e($propuesta->proposal)) !!}</p>

                    <h3>Beneficio esperado</h3>
                    <p>{!! nl2br(e($propuesta->expected_benefit)) !!}</p>

                    @if ($propuesta->impacts->isNotEmpty())
                        <h3>Por dónde mejora</h3>
                        <p>
                            @foreach ($propuesta->impacts as $impacto)
                                <span class="pill pill-teal">{{ $impacto->name }}</span>
                            @endforeach
                        </p>
                    @endif

                    @if ($propuesta->result_summary)
                        <h3>Resultado</h3>
                        <p>{!! nl2br(e($propuesta->result_summary)) !!}</p>
                    @endif
                </div>
            </div>

            {{-- El hilo que ve quien propuso --}}
            <div class="block">
                <div class="block-h"><span>Conversación</span></div>
                <div class="block-b">
                    <div class="hilo">
                        @forelse ($comentarios->where('is_internal', false) as $comentario)
                            <div class="mensaje">
                                <span class="quien">
                                    {{ $comentario->created_at->translatedFormat('j M · H:i') }} · {{ $comentario->authorName() }}
                                </span>
                                <p>{!! nl2br(e($comentario->body)) !!}</p>
                            </div>
                        @empty
                            <p style="font-size:13.5px; color:var(--ink-3)">Todavía no hay mensajes.</p>
                        @endforelse

                        @can('comment', $propuesta)
                            <form method="POST" action="{{ route('comments.store', $propuesta) }}" class="responder">
                                @csrf
                                <label for="cuerpo" class="visually-hidden">Escribe un mensaje</label>
                                <textarea id="cuerpo" name="cuerpo" required
                                          placeholder="{{ $propuesta->status->hasCode(\App\Models\ProposalStatus::AWAITING_INFO) && $esAutor
                                              ? 'Contesta aquí y tu propuesta volverá a revisión…'
                                              : 'Escribe un mensaje…' }}">{{ old('cuerpo') }}</textarea>
                                @error('cuerpo') <p class="error">{{ $message }}</p> @enderror
                                <div><button type="submit" class="btn btn-sm btn-pri">Enviar mensaje</button></div>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- La evaluación interna, solo comité --}}
            @if ($esComite)
                <div class="block">
                    <div class="block-h">
                        <span>Evaluación interna</span>
                        <span class="tag">Solo comité</span>
                    </div>
                    <div class="block-b">
                        <div class="hilo">
                            @forelse ($comentarios->where('is_internal', true) as $comentario)
                                <div class="mensaje interno">
                                    <span class="quien">
                                        {{ $comentario->created_at->translatedFormat('j M · H:i') }} · {{ $comentario->user->name }}
                                    </span>
                                    <p>{!! nl2br(e($comentario->body)) !!}</p>
                                </div>
                            @empty
                                <p style="font-size:13.5px; color:var(--ink-3)">Sin notas de evaluación todavía.</p>
                            @endforelse

                            @can('commentInternally', $propuesta)
                                <form method="POST" action="{{ route('comments.store', $propuesta) }}" class="responder">
                                    @csrf
                                    <input type="hidden" name="interno" value="1">
                                    <label for="interno_cuerpo" class="visually-hidden">Nota de evaluación</label>
                                    <textarea id="interno_cuerpo" name="cuerpo" required
                                              placeholder="Nota de evaluación. Quien propuso no la verá…"></textarea>
                                    <div><button type="submit" class="btn btn-sm">Guardar nota</button></div>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- -------------------------------------------------- Columna derecha --}}
        <div class="col">
            <div class="block">
                <div class="block-h"><span>Gestión</span></div>
                <div class="block-b">
                    <dl class="dl">
                        <dt>Estado</dt>
                        <dd><span class="pill pill-{{ $propuesta->status->color }}">{{ $propuesta->status->name }}</span></dd>

                        <dt>Revisor</dt>
                        <dd class="{{ $propuesta->reviewer ? '' : 'vacio' }}">{{ $propuesta->reviewer?->name ?? 'Sin asignar' }}</dd>

                        <dt>Sesión del comité</dt>
                        <dd class="{{ $propuesta->committeeSession ? '' : 'vacio' }}">
                            {{ $propuesta->committeeSession?->held_on->format('d/m/Y') ?? '—' }}
                        </dd>

                        <dt>Fecha decisión</dt>
                        <dd class="{{ $propuesta->decided_at ? '' : 'vacio' }}">
                            {{ $propuesta->decided_at?->format('d/m/Y') ?? '—' }}
                        </dd>

                        @if ($propuesta->revisit_on)
                            <dt>Se revisa el</dt>
                            <dd>{{ $propuesta->revisit_on->format('d/m/Y') }}</dd>
                        @endif

                        <dt>Responsable</dt>
                        <dd class="{{ $propuesta->implementer ? '' : 'vacio' }}">{{ $propuesta->implementer?->name ?? '—' }}</dd>

                        <dt>Inicio previsto</dt>
                        <dd class="{{ $propuesta->planned_start_on ? '' : 'vacio' }}">
                            {{ $propuesta->planned_start_on?->format('d/m/Y') ?? '—' }}
                        </dd>

                        <dt>Fin previsto</dt>
                        <dd class="{{ $propuesta->planned_end_on ? '' : 'vacio' }}">
                            {{ $propuesta->planned_end_on?->format('d/m/Y') ?? '—' }}
                        </dd>

                        @if ($propuesta->closed_on)
                            <dt>Cerrada el</dt>
                            <dd>{{ $propuesta->closed_on->format('d/m/Y') }}</dd>
                        @endif
                    </dl>

                    @if ($propuesta->decision_reason)
                        <div class="divisor"></div>
                        <p style="font-size:13px; color:var(--ink-3); font-family:'IBM Plex Mono',monospace">Motivo de la decisión</p>
                        <p style="font-size:13.5px; margin-top:4px">{{ $propuesta->decision_reason }}</p>
                    @endif
                </div>
            </div>

            {{-- Los botones del comité, solo los que el flujo permite ahora mismo --}}
            @if ($puedeRevisar || $puedeDecidir || $puedeImplantar)
                <div class="block">
                    <div class="block-h"><span>Acciones</span></div>
                    <div class="block-b" style="display:grid; gap:10px">

                        @if ($puedeRevisar && ! $propuesta->reviewer_id && in_array(\App\Models\ProposalStatus::IN_REVIEW, $siguientes, true))
                            <form method="POST" action="{{ route('proposals.assign', $propuesta) }}">
                                @csrf
                                <button type="submit" class="btn btn-pri btn-sm">Asignarme esta propuesta</button>
                            </form>
                        @endif

                        @if ($puedeRevisar && in_array(\App\Models\ProposalStatus::AWAITING_INFO, $siguientes, true))
                            <details class="accion">
                                <summary>Pedir información</summary>
                                <div class="cuerpo">
                                    <form method="POST" action="{{ route('proposals.requestInfo', $propuesta) }}" style="display:grid; gap:10px">
                                        @csrf
                                        <label for="pregunta">¿Qué necesitas saber?</label>
                                        <textarea id="pregunta" name="pregunta" required placeholder="La verá quien propuso, en el hilo."></textarea>
                                        <div><button type="submit" class="btn btn-sm">Enviar pregunta</button></div>
                                    </form>
                                </div>
                            </details>
                        @endif

                        @if ($puedeRevisar && in_array(\App\Models\ProposalStatus::IN_COMMITTEE, $siguientes, true))
                            <details class="accion">
                                <summary>Llevar al comité</summary>
                                <div class="cuerpo">
                                    <form method="POST" action="{{ route('proposals.toCommittee', $propuesta) }}" style="display:grid; gap:10px">
                                        @csrf
                                        <label for="fecha">Fecha de la sesión</label>
                                        <input type="date" id="fecha" name="fecha" required value="{{ now()->addWeek()->format('Y-m-d') }}">
                                        <div><button type="submit" class="btn btn-sm">Añadir al orden del día</button></div>
                                    </form>
                                </div>
                            </details>
                        @endif

                        @if ($puedeDecidir)
                            <details class="accion">
                                <summary>Decidir</summary>
                                <div class="cuerpo">
                                    <form method="POST" action="{{ route('proposals.decide', $propuesta) }}" style="display:grid; gap:12px">
                                        @csrf
                                        <div class="choices">
                                            <label class="choice"><input type="radio" name="decision" value="aprobar" checked> Aprobar</label>
                                            <label class="choice"><input type="radio" name="decision" value="rechazar"> Rechazar</label>
                                            <label class="choice"><input type="radio" name="decision" value="aplazar"> Aplazar</label>
                                        </div>
                                        <label for="motivo">Motivo <span class="hint">(obligatorio al rechazar y al aplazar)</span></label>
                                        <textarea id="motivo" name="motivo" placeholder="Esto es lo que se le comunica a quien propuso."></textarea>
                                        <label for="revisar_el">Si se aplaza, ¿cuándo se vuelve a mirar?</label>
                                        <input type="date" id="revisar_el" name="revisar_el" value="{{ now()->addMonths(3)->format('Y-m-d') }}">
                                        <div><button type="submit" class="btn btn-pri btn-sm">Guardar decisión</button></div>
                                    </form>
                                </div>
                            </details>
                        @endif

                        @if ($puedeImplantar)
                            <details class="accion">
                                <summary>Planificar implantación</summary>
                                <div class="cuerpo">
                                    <form method="POST" action="{{ route('proposals.plan', $propuesta) }}" style="display:grid; gap:10px">
                                        @csrf
                                        <label for="responsable">Responsable</label>
                                        <select id="responsable" name="responsable" required>
                                            @foreach ($responsables as $persona)
                                                <option value="{{ $persona->id }}" @selected($propuesta->implementer_id === $persona->id)>
                                                    {{ $persona->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <label for="inicio">Inicio previsto</label>
                                        <input type="date" id="inicio" name="inicio" required
                                               value="{{ $propuesta->planned_start_on?->format('Y-m-d') ?? now()->format('Y-m-d') }}">
                                        <label for="fin">Fin previsto</label>
                                        <input type="date" id="fin" name="fin" required
                                               value="{{ $propuesta->planned_end_on?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d') }}">
                                        <div><button type="submit" class="btn btn-sm">Guardar plan</button></div>
                                    </form>
                                </div>
                            </details>

                            <details class="accion">
                                <summary>Marcar como implantada</summary>
                                <div class="cuerpo">
                                    <form method="POST" action="{{ route('proposals.implemented', $propuesta) }}" style="display:grid; gap:10px">
                                        @csrf
                                        <label for="resultado">¿Qué se ha conseguido?</label>
                                        <textarea id="resultado" name="resultado" required
                                                  placeholder="Se comunica a quien propuso, y a toda la empresa si la propuesta es pública."></textarea>
                                        <label for="cerrada_el">Fecha de cierre</label>
                                        <input type="date" id="cerrada_el" name="cerrada_el" value="{{ now()->format('Y-m-d') }}">
                                        <div><button type="submit" class="btn btn-pri btn-sm">Cerrar como implantada</button></div>
                                    </form>
                                </div>
                            </details>
                        @endif

                        @if ($puedeRevisar && in_array(\App\Models\ProposalStatus::IN_REVIEW, $siguientes, true) && $propuesta->reviewer_id)
                            <details class="accion">
                                <summary>Reabrir</summary>
                                <div class="cuerpo">
                                    <form method="POST" action="{{ route('proposals.reopen', $propuesta) }}" style="display:grid; gap:10px">
                                        @csrf
                                        <label for="motivo_reapertura">¿Por qué se reabre?</label>
                                        <textarea id="motivo_reapertura" name="motivo" required placeholder="Han cambiado las circunstancias…"></textarea>
                                        <div><button type="submit" class="btn btn-sm">Volver a revisión</button></div>
                                    </form>
                                </div>
                            </details>
                        @endif

                        @if ($siguientes === [] && ! $puedeImplantar)
                            <p style="font-size:13px; color:var(--ink-3)">Esta propuesta está cerrada. No hay más pasos.</p>
                        @endif
                    </div>
                </div>
            @endif

            <div class="block">
                <div class="block-h">
                    <span>Historial</span>
                    <span>{{ $propuesta->statusChanges->count() }}</span>
                </div>
                <div class="block-b">
                    <ul class="tl">
                        @forelse ($propuesta->statusChanges as $cambio)
                            <li>
                                <span class="dot done"></span>
                                <span>
                                    <b>{{ $cambio->fromStatus ? $cambio->toStatus->name : 'Registrada' }}</b>
                                    <small>
                                        @if ($propuesta->isAnonymous() && $cambio->user_id === $propuesta->user_id)
                                            Proponente
                                        @else
                                            {{ $cambio->user->name }}
                                        @endif
                                    </small>
                                    <em>{{ $cambio->created_at->translatedFormat('j M Y · H:i') }}</em>
                                    @if ($cambio->comment && $esComite)
                                        <q>{{ Str::limit($cambio->comment, 160) }}</q>
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li style="grid-template-columns:1fr">
                                <span style="font-size:13.5px; color:var(--ink-3)">Sin movimientos todavía.</span>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
