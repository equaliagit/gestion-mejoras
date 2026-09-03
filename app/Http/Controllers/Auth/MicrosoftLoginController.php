<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Entrada con la cuenta de Microsoft 365 de la empresa.
 *
 * El registro en Entra ID es de un solo inquilino, así que aquí solo puede
 * llegar gente de la casa: nadie con una cuenta personal puede pasar de la
 * pantalla de Microsoft.
 *
 * Quien entra por primera vez se da de alta solo, con el rol de Empleado.
 * Nadie tiene que mantener una lista de usuarios, y cuando a alguien le
 * quitan la cuenta de Microsoft al marcharse, deja de poder entrar sin que
 * haya que acordarse de nada. Los roles de comité y administración sí se
 * asignan a mano: eso no lo hereda nadie por tener correo de la empresa.
 */
class MicrosoftLoginController extends Controller
{
    /** Manda a la persona a la pantalla de Microsoft. */
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! $this->configurado()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'La entrada con Microsoft todavía no está configurada.']);
        }

        // Solo lo que necesitamos: saber quién eres. Sin `offline_access`,
        // que la librería añade por defecto y sirve para renovar la sesión
        // por detrás — nosotros no lo usamos, y en la pantalla de permisos
        // aparece como «mantener el acceso a los datos», que asusta con razón.
        return Socialite::driver('azure')
            ->setScopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    /** Aquí vuelve Microsoft con la respuesta. */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configurado()) {
            return redirect()->route('login');
        }

        try {
            $cuenta = Socialite::driver('azure')->user();
        } catch (Throwable $e) {
            Log::warning('Falló la entrada con Microsoft: '.$e->getMessage());

            return redirect()->route('login')->withErrors([
                'email' => 'No hemos podido comprobar tu cuenta de Microsoft. Vuelve a intentarlo.',
            ]);
        }

        $correo = mb_strtolower(trim((string) ($cuenta->getEmail() ?: $cuenta->getNickname())));

        if ($correo === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta de Microsoft no tiene un correo asociado. Habla con el administrador.',
            ]);
        }

        $usuario = $this->encontrarOCrear($correo, (string) $cuenta->getName(), (string) $cuenta->getId());

        if (! $usuario->is_active) {
            return redirect()->route('login')->withErrors([
                'email' => 'Esta cuenta está dada de baja. Habla con el administrador.',
            ]);
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        $usuario->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('proposals.index'));
    }

    /**
     * Busca a la persona por su identificador de Microsoft y, si no aparece,
     * por su correo: así quien ya tenía cuenta —con su rol de comité, por
     * ejemplo— la conserva al pasarse a Microsoft, en vez de duplicarse.
     */
    private function encontrarOCrear(string $correo, string $nombre, string $microsoftId): User
    {
        $usuario = User::query()->where('microsoft_id', $microsoftId)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$correo])->first();

        if ($usuario) {
            $usuario->forceFill(['microsoft_id' => $microsoftId])->save();

            return $usuario;
        }

        // Sin área: Microsoft no sabe en cuál trabaja cada uno, y poner una
        // por defecto sería peor que dejarla vacía — el formulario la traería
        // preseleccionada y la gente enviaría propuestas mal clasificadas sin
        // enterarse, con lo que los informes por área dejarían de valer.
        $nuevo = User::create([
            'name' => $nombre !== '' ? $nombre : $correo,
            'email' => $correo,
            'microsoft_id' => $microsoftId,
            'is_active' => true,
        ]);

        $nuevo->assignRole('Empleado');

        Log::info('Alta automática por Microsoft', ['email' => $correo]);

        return $nuevo->fresh();
    }

    /** Si falta cualquiera de los tres datos, la puerta no existe. */
    private function configurado(): bool
    {
        return filled(config('services.azure.client_id'))
            && filled(config('services.azure.client_secret'))
            && filled(config('services.azure.redirect'));
    }
}
