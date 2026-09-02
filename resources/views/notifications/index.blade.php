@extends('layouts.app')

@section('titulo', 'Avisos · Buzón de Mejora')

@section('contenido')
    <div class="topbar">
        <div class="grow">
            <h1>Avisos</h1>
            <p class="sub">Lo mismo que te llega por correo, guardado aquí dentro.</p>
        </div>
        @if ($avisos->whereNull('read_at')->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read') }}">
                @csrf
                <button type="submit" class="btn btn-sm">Marcar todo como leído</button>
            </form>
        @endif
    </div>

    @forelse ($avisos as $aviso)
        @if ($loop->first)
            <div class="block"><div class="block-b" style="padding:0">
        @endif

        <div class="ninfo">
            <span class="u {{ $aviso->read_at ? 'leido' : '' }}"></span>
            <span>
                @if ($aviso->data['proposal_id'] ?? null)
                    <a class="tt" href="{{ route('notifications.open', $aviso->id) }}">
                        {{ $aviso->data['titular'] }}
                    </a>
                @else
                    <b>{{ $aviso->data['titular'] }}</b>
                @endif
                <p>{{ $aviso->data['detalle'] }}</p>
                <time>{{ $aviso->created_at->translatedFormat('j M · H:i') }}</time>
            </span>
        </div>

        @if ($loop->last)
            </div></div>
        @endif
    @empty
        <div class="empty">
            <h2>Ningún aviso todavía</h2>
            <p>Aquí aparecerán los avisos de tus propuestas: cuando alguien las revise, te pregunte algo o haya decisión.</p>
        </div>
    @endforelse
@endsection
