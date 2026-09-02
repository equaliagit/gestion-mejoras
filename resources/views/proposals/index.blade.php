@extends('layouts.app')

@section('titulo', 'Mis propuestas · Buzón de Mejora')

@section('contenido')
    <div class="topbar">
        <div class="grow">
            <h1>Mis propuestas</h1>
            <p class="sub">Todo lo que has registrado y en qué punto está cada cosa.</p>
        </div>
        <a class="btn btn-pri" href="{{ route('proposals.create') }}">+ Nueva propuesta</a>
    </div>

    @if ($propuestas->isNotEmpty())
        <div class="kpis">
            <div class="kpi"><div class="n">{{ $resumen['total'] }}</div><div class="l">Registradas</div></div>
            <div class="kpi"><div class="n">{{ $resumen['abiertas'] }}</div><div class="l">En curso</div></div>
            <div class="kpi"><div class="n">{{ $resumen['implantadas'] }}</div><div class="l">Implantadas</div></div>
            <div class="kpi"><div class="n">{{ $resumen['borradores'] }}</div><div class="l">Borradores</div></div>
        </div>
    @endif

    @forelse ($propuestas as $propuesta)
        @if ($loop->first)
            <div class="tablewrap">
            <table>
                <caption class="visually-hidden">Listado de tus propuestas de mejora</caption>
                <thead>
                    <tr>
                        <th scope="col">Ref.</th>
                        <th scope="col">Título</th>
                        <th scope="col">Área</th>
                        <th scope="col">Prioridad</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Actualizada</th>
                    </tr>
                </thead>
                <tbody>
        @endif

            <tr>
                <td class="ref">{{ $propuesta->reference ?? '—' }}</td>
                <td>
                    <a class="tt" href="{{ route('proposals.show', $propuesta) }}">{{ $propuesta->title }}</a>
                    <small>
                        @if ($propuesta->isDraft())
                            Borrador sin enviar
                        @elseif ($propuesta->status->hasCode(\App\Models\ProposalStatus::AWAITING_INFO))
                            <span class="pide-accion">Te han preguntado algo · contesta en la ficha</span>
                        @elseif ($propuesta->reviewer)
                            Revisa: {{ $propuesta->reviewer->name }}
                        @else
                            Pendiente de asignar
                        @endif
                        @if ($propuesta->visibility !== \App\Enums\Visibility::Public)
                            · <span class="tag">{{ $propuesta->visibility->label() }}</span>
                        @endif
                    </small>
                </td>
                <td>{{ $propuesta->area->name }}</td>
                <td>
                    <span class="prio prio-{{ $propuesta->effectivePriority()->value }}">
                        {{ mb_strtoupper($propuesta->effectivePriority()->label()) }}
                    </span>
                </td>
                <td>
                    <span class="pill pill-{{ $propuesta->status->color }}">{{ $propuesta->status->name }}</span>
                </td>
                <td class="num">{{ $propuesta->updated_at->translatedFormat('d M') }}</td>
            </tr>

        @if ($loop->last)
                </tbody>
            </table>
            </div>
        @endif
    @empty
        <div class="empty">
            <h2>Todavía no has propuesto nada</h2>
            <p>Si algo te parece mejorable en tu día a día, cuéntalo. No hace falta que sea una gran idea ni que esté todo pensado.</p>
            <a class="btn btn-pri" href="{{ route('proposals.create') }}">Escribir mi primera propuesta</a>
        </div>
    @endforelse
@endsection
