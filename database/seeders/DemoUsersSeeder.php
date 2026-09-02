<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de prueba para trabajar en local. NO se lanza en el servidor:
 * allí las cuentas las crea el administrador (y más adelante, Microsoft 365).
 *
 *   php artisan db:seed --class=DemoUsersSeeder
 *
 * Todos con la misma contraseña: buzon1234
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->warn('Este seeder es solo para el entorno local. No se ha hecho nada.');

            return;
        }

        $usuarios = [
            ['Marta Ruiz', 'marta@miempresa.es', 'Empleado', 'Operaciones'],
            ['Carlos Vidal', 'carlos@miempresa.es', 'Empleado', 'Logística'],
            ['Luis Peña', 'luis@miempresa.es', 'Comité', 'Operaciones'],
            ['Nuria Sanz', 'nuria@miempresa.es', 'Comité', 'RRHH'],
            ['Jorge Soporte', 'jorge@miempresa.es', 'Soporte técnico', 'Desarrollo'],
        ];

        foreach ($usuarios as [$nombre, $correo, $rol, $area]) {
            $user = User::updateOrCreate(
                ['email' => $correo],
                [
                    'name' => $nombre,
                    'password' => 'buzon1234',
                    'area_id' => Area::where('name', $area)->value('id'),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$rol]);
        }

        $this->command->info('5 usuarios de prueba listos. Contraseña para todos: buzon1234');
    }
}
