{{--
  La plantilla base. Todas las pantallas «heredan» de aquí con @extends y
  rellenan el hueco @yield('contenido'). Es el equivalente a un layout de
  Thymeleaf o a una plantilla maestra de JSP.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Buzón de Mejora')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="app">
    <aside class="side">
        <a class="brand" href="{{ route('proposals.index') }}">
            <svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true">
                <rect x="1" y="1" width="24" height="24" rx="7" fill="none" stroke="currentColor" stroke-opacity=".25"/>
                <path d="M7 17.5 L11.5 11 L15 14.5 L19.5 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="19.5" cy="8" r="2.2" fill="currentColor"/>
            </svg>
            <span><b>Buzón de Mejora</b><span>Intranet</span></span>
        </a>

        <nav class="navgroup" aria-label="Mi espacio">
            <p class="eyebrow">Mi espacio</p>
            <a class="nav {{ request()->routeIs('proposals.index') ? 'is-on' : '' }}" href="{{ route('proposals.index') }}">
                Mis propuestas
                @if ($misPropuestas ?? false)
                    <span class="count">{{ $misPropuestas }}</span>
                @endif
            </a>
            <a class="nav {{ request()->routeIs('proposals.shared') ? 'is-on' : '' }}" href="{{ route('proposals.shared') }}">
                Propuestas de la empresa
            </a>
            <a class="nav {{ request()->routeIs('proposals.create') ? 'is-on' : '' }}" href="{{ route('proposals.create') }}">
                Nueva propuesta
            </a>
            <a class="nav {{ request()->routeIs('notifications.index') ? 'is-on' : '' }}" href="{{ route('notifications.index') }}">
                Avisos
                @if (($avisosSinLeer ?? 0) > 0)
                    <span class="count sin-leer">{{ $avisosSinLeer }}</span>
                @endif
            </a>
        </nav>

        @if (auth()->user()->canAny(['proposals.review', 'reports.view', 'users.manage']))
            <nav class="navgroup" aria-label="Gestión">
                <p class="eyebrow">Gestión</p>
                @can('proposals.review')
                    <a class="nav {{ request()->routeIs('committee.inbox') ? 'is-on' : '' }}" href="{{ route('committee.inbox') }}">
                        Bandeja del comité
                    </a>
                @endcan
                @can('reports.view')
                    <a class="nav {{ request()->routeIs('reports.*') ? 'is-on' : '' }}" href="{{ route('reports.index') }}">
                        Informes
                    </a>
                @endcan
                @can('users.manage')
                    <a class="nav {{ request()->routeIs('users.*') ? 'is-on' : '' }}" href="{{ route('users.index') }}">
                        Personas
                    </a>
                @endcan
            </nav>
        @endif

        <div class="who">
            <span class="avatar">{{ $iniciales ?? '' }}</span>
            <span>
                <b>{{ auth()->user()->name }}</b>
                <small>{{ auth()->user()->area?->name ?? 'Sin área' }}</small>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="linkbtn">Salir</button>
            </form>
        </div>
    </aside>

    <main class="main">
        @if (session('exito'))
            <p class="flash">{{ session('exito') }}</p>
        @endif
        @if (session('error'))
            <p class="flash flash-bad">{{ session('error') }}</p>
        @endif

        @yield('contenido')
    </main>
</div>
</body>
</html>
