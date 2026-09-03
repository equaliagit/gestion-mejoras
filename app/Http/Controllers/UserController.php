<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Alta y mantenimiento de personas.
 *
 * Aquí no se borra a nadie: se da de baja. Quien ha propuesto algo sigue
 * apareciendo en el historial de su propuesta, y borrarlo dejaría huérfanas
 * las filas que le apuntan. Dar de baja cierra la puerta y conserva el pasado.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('buscar'));

        return view('users.index', [
            'usuarios' => User::query()
                ->when($busqueda !== '', fn ($q) => $q->where(
                    fn ($sub) => $sub->where('name', 'like', "%{$busqueda}%")
                        ->orWhere('email', 'like', "%{$busqueda}%")
                ))
                ->with(['area', 'roles'])
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get(),
            'busqueda' => $busqueda,
            'total' => User::count(),
            'activos' => User::where('is_active', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'usuario' => new User(['is_active' => true]),
            'areas' => Area::active()->get(),
            'roles' => array_keys(Permissions::roles()),
            'suyos' => ['Empleado'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $usuario = User::create([
            'name' => $datos['name'],
            'email' => mb_strtolower($datos['email']),
            'area_id' => $datos['area_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'password' => filled($datos['password'] ?? null) ? $datos['password'] : null,
        ]);

        $usuario->syncRoles($datos['roles']);

        return redirect()
            ->route('users.index')
            ->with('exito', "{$usuario->name} ya puede entrar en el Buzón.");
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'usuario' => $user,
            'areas' => Area::active()->get(),
            'roles' => array_keys(Permissions::roles()),
            'suyos' => $user->roles->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $datos = $this->validar($request, $user);

        $this->comprobarQueNoTeCierrasLaPuerta($request, $user, $datos['roles']);

        $user->fill([
            'name' => $datos['name'],
            'email' => mb_strtolower($datos['email']),
            'area_id' => $datos['area_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (filled($datos['password'] ?? null)) {
            $user->password = $datos['password'];
        }

        $user->save();
        $user->syncRoles($datos['roles']);

        return redirect()
            ->route('users.index')
            ->with('exito', "Cambios guardados en {$user->name}.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys(Permissions::roles()))],
        ], [
            'name.required' => 'Escribe el nombre.',
            'email.required' => 'Escribe el correo.',
            'email.unique' => 'Ya hay alguien con ese correo.',
            'password.min' => 'La contraseña necesita ocho caracteres como mínimo.',
            'roles.required' => 'Marca al menos un rol.',
            'roles.min' => 'Marca al menos un rol.',
        ]);
    }

    /**
     * La red de seguridad: nadie puede quitarse a sí mismo la capacidad de
     * administrar ni darse de baja. Sin esto, un despiste deja la aplicación
     * sin ningún administrador y hay que entrar a la base de datos a mano.
     *
     * @param  list<string>  $rolesNuevos
     */
    private function comprobarQueNoTeCierrasLaPuerta(Request $request, User $user, array $rolesNuevos): void
    {
        if ($request->user()->id !== $user->id) {
            return;
        }

        if (! $request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'No puedes darte de baja a ti mismo. Que lo haga otra persona con permisos.',
            ]);
        }

        $seguiriaAdministrando = collect(Permissions::roles())
            ->only($rolesNuevos)
            ->flatten()
            ->contains(Permissions::MANAGE_USERS);

        if (! $seguiriaAdministrando) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes quitarte a ti mismo el permiso de administrar usuarios: te quedarías fuera y habría que arreglarlo desde la base de datos.',
            ]);
        }
    }
}
