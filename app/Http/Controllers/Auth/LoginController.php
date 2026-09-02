<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Entrada y salida con correo y contraseña.
 *
 * Está escrito a mano y no con el andamiaje que trae Laravel porque no
 * necesitamos registro público ni recuperación de contraseña: las cuentas las
 * crea el administrador, y a medio plazo se entrará con Microsoft 365. Cuando
 * llegue ese momento se añade otro método aquí y este se queda como respaldo.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Ese correo no parece válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        // Freno a la fuerza bruta: cinco intentos por correo y dirección.
        $clave = mb_strtolower($datos['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera un minuto y vuelve a probar.',
            ]);
        }

        if (! Auth::attempt($datos, $request->boolean('remember'))) {
            RateLimiter::hit($clave, decaySeconds: 60);

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        if (! $request->user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta está dada de baja. Habla con el administrador.',
            ]);
        }

        RateLimiter::clear($clave);

        // Cambiar el identificador de sesión al entrar evita el robo de sesión.
        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('proposals.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
