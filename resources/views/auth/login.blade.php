<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Buzón de Mejora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="login">
    <div class="login-card">
        <div class="login-head">
            <svg width="34" height="34" viewBox="0 0 26 26" aria-hidden="true" style="color:var(--accent)">
                <rect x="1" y="1" width="24" height="24" rx="7" fill="none" stroke="currentColor" stroke-opacity=".3"/>
                <path d="M7 17.5 L11.5 11 L15 14.5 L19.5 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="19.5" cy="8" r="2.2" fill="currentColor"/>
            </svg>
            <h1>Buzón de Mejora</h1>
            <p>Entra con tu cuenta para proponer y seguir tus ideas.</p>
        </div>

        @if ($errors->any())
            <p class="flash flash-bad" style="margin:0">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="stack" style="gap:16px">
            @csrf

            <div class="field">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <label class="choice" style="align-self:start">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                No cerrar sesión
            </label>

            <button type="submit" class="btn btn-pri" style="justify-content:center">Entrar</button>
        </form>
    </div>
</div>
</body>
</html>
