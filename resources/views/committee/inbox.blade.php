@extends('layouts.app')

@section('titulo', 'Bandeja del comité · Buzón de Mejora')

@section('contenido')
    <div class="topbar">
        <div class="grow">
            <h1>Bandeja del comité</h1>
            <p class="sub">{{ $contadores['abiertas'] }} propuestas abiertas, de la que más lleva esperando a la que menos.</p>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><div class="n">{{ $contadores['abiertas'] }}</div><div class="l">Abiertas</div></div>
        <div class="kpi"><div class="n">{{ $contadores['sin_asignar'] }}</div><div class="l">Sin asignar</div></div>
        <div class="kpi"><div class="n">{{ $contadores['mias'] }}</div><div class="l">A mi nombre</div></div>
        <div class="kpi"><div class="n">{{ $contadores['en_comite'] }}</div><div class="l">Esperando decisión</div></div>
    </div>

    <div class="chips">
        <a class="chip {{ $filtro ? '' : 'on' }}" href="{{ route('committee.inbox') }}">
            Abiertas <b>{{ $contadores['abiertas'] }}</b>
        </a>
        <a class="chip {{ $filtro === 'sin-asignar' ? 'on' : '' }}" href="{{ route('committee.inbox', ['estado' => 'sin-asignar']) }}">
            Sin asignar <b>{{ $contadores['sin_asignar'] }}</b>
        </a>
        <a class="chip {{ $filtro === 'mias' ? 'on' : '' }}" href="{{ route('committee.inbox', ['estado' => 'mias']) }}">
            A mi nombre <b>{{ $contadores['mias'] }}</b>
        </a>
        <a class="chip {{ $filtro === 'in_committee' ? 'on' : '' }}" href="{{ route('committee.inbox', ['estado' => 'in_committee']) }}">
            En comité <b>{{ $contadores['en_comite'] }}</b>
        </a>
        <a class="chip {{ $filtro === 'cerradas' ? 'on' : '' }}" href="{{ route('committee.inbox', ['estado' => 'cerradas']) }}">
            Todas
        </a>
    </div>

    @forelse ($propuestas as $propuesta)
        @if ($loop->first)
            <div class="tablewrap">
            <table>
                <caption class="visually-hidden">Propuestas abiertas del comité</caption>
                <thead>
                    <tr>
                        <th scope="col">Ref.</th>
                        <th scope="col">Propuesta</th>
                        <th scope="col">Área</th>
                        <th scope="col">Prio.</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Revisor</th>
                        <th scope="col">Días</th>
                    </tr>
                </thead>
                <tbody>
        @endif

            <tr>
                <td class="ref">{{ $propuesta->reference }}</td>
                <td>
                    <a class="tt" href="{{ route('proposals.show', $propuesta) }}">{{ $propuesta->title }}</a>
                    <small>
                        {{ $propuesta->authorName() }}
                        @if ($propuesta->visibility !== \App\Enums\Visibility::Public)
                            · <span class="tag tag-dashed">{{ $propuesta->visibility->label() }}</span>
                        @endif
                    </small>
                </td>
                <td>{{ $propuesta->area->name }}</td>
                <td>
                    <span class="prio prio-{{ $propuesta->effectivePriority()->value }}">
                        {{ mb_strtoupper($propuesta->effectivePriority()->label()) }}
                    </span>
                </td>
                <td><span class="pill pill-{{ $propuesta->status->color }}">{{ $propuesta->status->name }}</span></td>
                <td class="{{ $propuesta->reviewer ? '' : 'num' }}">{{ $propuesta->reviewer?->name ?? 'Sin asignar' }}</td>
                <td class="num">{{ (int) $propuesta->submitted_at->diffInDays(now()) }}</td>
            </tr>

        @if ($loop->last)
                </tbody>
            </table>
            </div>
        @endif
    @empty
        <div class="empty">
            <h2>Nada pendiente por aquí</h2>
            <p>No hay propuestas que encajen con este filtro. Buena señal, o pocas ideas: ya se verá en los informes.</p>
        </div>
    @endforelse
@endsection
