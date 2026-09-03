@extends('layouts.app')

@section('titulo', ($usuario->exists ? $usuario->name : 'Añadir persona').' · Buzón de Mejora')

@section('contenido')
    <p class="migas"><a href="{{ route('users.index') }}">Personas</a></p>

    <div class="topbar">
        <div class="grow">
            <h1>{{ $usuario->exists ? $usuario->name : 'Añadir persona' }}</h1>
            <p class="sub">
                {{ $usuario->exists
                    ? 'Roles, área y estado de la cuenta.'
                    : 'Solo hace falta darla de alta a mano si todavía no ha entrado con Microsoft.' }}
            </p>
        </div>
    </div>

    @if ($errors->any())
        <p class="flash flash-bad">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $usuario->exists ? route('users.update', $usuario) : route('users.store') }}" class="stack">
        @csrf
        @if ($usuario->exists) @method('PUT') @endif

        <div class="row">
            <div class="field">
                <label for="name">Nombre <span class="req">*</span></label>
                <input type="text" id="name" name="name" required
                       value="{{ old('name', $usuario->name) }}"
                       aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="email">Correo <span class="req">*</span></label>
                <input type="email" id="email" name="email" required
                       value="{{ old('email', $usuario->email) }}"
                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                <p class="hint">Tiene que ser el mismo que usa en Microsoft 365, o no se reconocerá al entrar.</p>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="field">
            <label for="area_id">Área</label>
            <select id="area_id" name="area_id">
                <option value="">Sin área</option>
                @foreach ($areas as $area)
                    <option value="{{ $area->id }}" @selected(old('area_id', $usuario->area_id) == $area->id)>
                        {{ $area->name }}
                    </option>
                @endforeach
            </select>
            <p class="hint">Solo sirve para traer el área puesta al escribir una propuesta. Se puede cambiar siempre.</p>
        </div>

        <div class="field">
            <label>Roles <span class="req">*</span></label>
            <div class="choices">
                @foreach ($roles as $rol)
                    <label class="choice">
                        <input type="checkbox" name="roles[]" value="{{ $rol }}"
                               @checked(in_array($rol, old('roles', $suyos)))>
                        {{ $rol }}
                    </label>
                @endforeach
            </div>
            <p class="hint">
                <strong>Empleado:</strong> proponer y seguir lo suyo. &nbsp;
                <strong>Comité:</strong> ve todas las propuestas, incluidas las privadas y las anónimas, y decide. &nbsp;
                <strong>Administración:</strong> gestiona personas y catálogos, sin ver las privadas. &nbsp;
                <strong>Soporte técnico:</strong> mantiene la aplicación, tampoco ve las privadas.
            </p>
            @error('roles') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="visib">
            <label class="choice" style="align-self:start">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $usuario->is_active ?? true))>
                Cuenta activa
            </label>
            <p class="hint">
                Al quitar la marca, la persona deja de poder entrar, pero sus propuestas y su historial se conservan.
                Es lo que hay que hacer cuando alguien se va, en lugar de borrarla.
            </p>
            @error('is_active') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" autocomplete="new-password"
                   placeholder="{{ $usuario->exists ? 'Dejar vacío para no cambiarla' : 'Opcional' }}">
            <p class="hint">
                Solo hace falta para quien vaya a entrar sin Microsoft. Si se deja vacío
                {{ $usuario->exists ? 'no se toca la que tuviera' : 'la persona entrará únicamente con su cuenta de Microsoft' }}.
            </p>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="formfoot">
            <a class="btn" href="{{ route('users.index') }}">Cancelar</a>
            <button type="submit" class="btn btn-pri">
                {{ $usuario->exists ? 'Guardar cambios' : 'Dar de alta' }}
            </button>
        </div>
    </form>
@endsection
