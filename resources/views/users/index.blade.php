@extends('layouts.app')

@section('titulo', 'Personas · Buzón de Mejora')

@section('contenido')
    <div class="topbar">
        <div class="grow">
            <h1>Personas</h1>
            <p class="sub">{{ $activos }} en activo de {{ $total }} · aquí se decide quién es del comité.</p>
        </div>
        <a class="btn btn-pri" href="{{ route('users.create') }}">+ Añadir persona</a>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="chips" style="margin-bottom:18px">
        <label for="buscar" class="visually-hidden">Buscar</label>
        <input type="text" id="buscar" name="buscar" value="{{ $busqueda }}"
               placeholder="Buscar por nombre o correo…" style="max-width:280px">
        <button type="submit" class="btn btn-sm">Buscar</button>
        @if ($busqueda !== '')
            <a class="chip" href="{{ route('users.index') }}">Quitar el filtro</a>
        @endif
    </form>

    @forelse ($usuarios as $usuario)
        @if ($loop->first)
            <div class="tablewrap">
            <table>
                <caption class="visually-hidden">Personas dadas de alta en el Buzón</caption>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Área</th>
                        <th scope="col">Roles</th>
                        <th scope="col">Entra con</th>
                        <th scope="col">Última vez</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
        @endif

            <tr>
                <td>
                    <a class="tt" href="{{ route('users.edit', $usuario) }}">{{ $usuario->name }}</a>
                    <small>{{ $usuario->email }}</small>
                </td>
                <td class="{{ $usuario->area ? '' : 'num' }}">{{ $usuario->area?->name ?? 'Sin área' }}</td>
                <td>
                    @forelse ($usuario->roles as $rol)
                        <span class="pill {{ $rol->name === 'Comité' ? 'pill-teal' : 'pill-gray' }}">{{ $rol->name }}</span>
                    @empty
                        <span class="tag">Sin rol</span>
                    @endforelse
                </td>
                <td>
                    @if ($usuario->usesSingleSignOn())
                        <span class="tag">Microsoft</span>
                    @elseif ($usuario->password)
                        <span class="tag">Contraseña</span>
                    @else
                        <span class="tag">— sin acceso</span>
                    @endif
                </td>
                <td class="num">{{ $usuario->last_login_at?->translatedFormat('d M Y') ?? 'Nunca' }}</td>
                <td>
                    @if ($usuario->is_active)
                        <span class="pill pill-green">Activo</span>
                    @else
                        <span class="pill pill-red">De baja</span>
                    @endif
                </td>
            </tr>

        @if ($loop->last)
                </tbody>
            </table>
            </div>
        @endif
    @empty
        <div class="empty">
            <h2>Nadie coincide con esa búsqueda</h2>
            <p>Prueba con otro nombre o quita el filtro para verlos a todos.</p>
        </div>
    @endforelse

    <div class="nota-pie">
        <p><strong>Aquí no se borra a nadie, se da de baja.</strong> Quien ha propuesto algo sigue apareciendo en el
        historial de su propuesta; borrarlo dejaría el pasado incompleto. Dar de baja cierra la puerta y conserva
        lo que hizo.</p>
    </div>
@endsection
