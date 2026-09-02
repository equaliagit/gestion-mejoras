@extends('layouts.app')

@section('titulo', 'Propuestas de la empresa · Buzón de Mejora')

@section('contenido')
    <div class="topbar">
        <div class="grow">
            <h1>Propuestas de la empresa</h1>
            <p class="sub">Las que tus compañeros han querido compartir con todos. Las privadas y las anónimas no aparecen aquí.</p>
        </div>
        <a class="btn btn-pri" href="{{ route('proposals.create') }}">+ Nueva propuesta</a>
    </div>

    @forelse ($propuestas as $propuesta)
        @if ($loop->first)
            <div class="tablewrap">
            <table>
                <caption class="visually-hidden">Propuestas públicas de la empresa</caption>
                <thead>
                    <tr>
                        <th scope="col">Ref.</th>
                        <th scope="col">Título</th>
                        <th scope="col">Propone</th>
                        <th scope="col">Área</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Enviada</th>
                    </tr>
                </thead>
                <tbody>
        @endif

            <tr>
                <td class="ref">{{ $propuesta->reference }}</td>
                <td>
                    <a class="tt" href="{{ route('proposals.show', $propuesta) }}">{{ $propuesta->title }}</a>
                    @if ($propuesta->status->hasCode(\App\Models\ProposalStatus::IMPLEMENTED) && $propuesta->result_summary)
                        <small>{{ Str::limit($propuesta->result_summary, 90) }}</small>
                    @endif
                </td>
                <td>{{ $propuesta->authorName() }}</td>
                <td>{{ $propuesta->area->name }}</td>
                <td><span class="pill pill-{{ $propuesta->status->color }}">{{ $propuesta->status->name }}</span></td>
                <td class="num">{{ $propuesta->submitted_at->translatedFormat('d M') }}</td>
            </tr>

        @if ($loop->last)
                </tbody>
            </table>
            </div>
        @endif
    @empty
        <div class="empty">
            <h2>Todavía no hay propuestas públicas</h2>
            <p>Cuando alguien registre una propuesta y elija compartirla con toda la empresa, aparecerá en esta lista.</p>
            <a class="btn btn-pri" href="{{ route('proposals.create') }}">Escribir la primera</a>
        </div>
    @endforelse
@endsection
