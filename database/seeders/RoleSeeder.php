<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los diez permisos y los cuatro roles. Relanzable: sincroniza los
 * permisos de cada rol con lo que diga App\Support\Permissions, que es la
 * única fuente de verdad.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Los permisos recién creados no están en la caché que el paquete
        // cargó hace un momento; sin este olvido, syncPermissions no los ve.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::roles() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
