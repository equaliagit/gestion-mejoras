<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Las tareas de despliegue, desde el navegador.
 *
 * Este alojamiento no da acceso por consola, así que no hay forma de escribir
 * `php artisan migrate` en el servidor. Esta dirección lo suple, y está
 * cerrada con tres cerrojos a la vez:
 *
 *   1. Una llave larga y secreta, distinta de la del cron.
 *   2. Un interruptor en el .env que hay que encender a propósito
 *      (MAINTENANCE_ENABLED) y apagar al terminar.
 *   3. Una lista cerrada de tareas: no ejecuta lo que le pidan, solo estas
 *      cuatro. No hay forma de colar un comando arbitrario.
 *
 * Con el interruptor apagado, la dirección responde «no existe» aunque se
 * acierte la llave. Ese es el estado normal: solo se enciende el rato que
 * dura una actualización.
 */
class MaintenanceController extends Controller
{
    /** Lo único que se puede ejecutar desde aquí. */
    private const TAREAS = [
        'estado' => 'Qué migraciones hay aplicadas y cuáles faltan',
        'migrar' => 'Crear o actualizar las tablas',
        'sembrar' => 'Cargar áreas, estados, impactos, roles y permisos',
        'limpiar' => 'Vaciar la caché de configuración, rutas y vistas',
        'administrador' => 'Dar permisos de administración a quien indique ?correo=',
    ];

    public function __invoke(Request $request, string $llave, string $tarea): JsonResponse
    {
        $this->comprobarLaPuerta($llave, $tarea);

        $salida = match ($tarea) {
            'estado' => $this->ejecutar('migrate:status'),
            'migrar' => $this->ejecutar('migrate', ['--force' => true]),
            'sembrar' => $this->sembrar(),
            'limpiar' => $this->ejecutar('optimize:clear'),
            'administrador' => $this->hacerAdministrador((string) $request->query('correo', '')),
        };

        Log::info("Tarea de mantenimiento ejecutada: {$tarea}");

        return response()->json([
            'ok' => true,
            'tarea' => $tarea,
            'descripcion' => self::TAREAS[$tarea],
            'salida' => $salida,
            'recuerda' => 'Cuando termines, pon MAINTENANCE_ENABLED=false en el .env.',
        ]);
    }

    private function comprobarLaPuerta(string $llave, string $tarea): void
    {
        $esperada = (string) config('buzon.maintenance_key');

        if (! config('buzon.maintenance_enabled')
            || $esperada === ''
            || ! hash_equals($esperada, $llave)
            || ! array_key_exists($tarea, self::TAREAS)) {
            throw new NotFoundHttpException;
        }
    }

    /** @param array<string, mixed> $parametros */
    private function ejecutar(string $comando, array $parametros = []): string
    {
        Artisan::call($comando, $parametros);

        return trim(Artisan::output());
    }

    /**
     * El primer administrador, que es el problema del huevo y la gallina:
     * la pantalla de personas exige permisos de administración, y en un
     * servidor recién montado no los tiene nadie.
     *
     * No crea la cuenta —eso lo hace el propio Microsoft al entrar la primera
     * vez, o el seeder—: solo asciende a alguien que ya existe. Así esta
     * dirección nunca puede inventarse un usuario de la nada.
     */
    private function hacerAdministrador(string $correo): string
    {
        $correo = mb_strtolower(trim($correo));

        if ($correo === '') {
            return 'Falta indicar a quién: añade ?correo=persona@empresa.es al final de la dirección.';
        }

        $usuario = User::query()->whereRaw('LOWER(email) = ?', [$correo])->first();

        if (! $usuario) {
            return "No hay nadie con el correo {$correo}. Entra primero con esa cuenta y vuelve a lanzar esta tarea.";
        }

        $usuario->assignRole('Administración');

        return "{$usuario->name} ya puede administrar personas. Entra en /personas y termina desde ahí.";
    }

    private function sembrar(): string
    {
        return $this->ejecutar('db:seed', ['--class' => 'Database\Seeders\CatalogSeeder', '--force' => true])
            ."\n"
            .$this->ejecutar('db:seed', ['--class' => 'Database\Seeders\RoleSeeder', '--force' => true]);
    }
}
