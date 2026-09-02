<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Deja la base de datos lista para trabajar: áreas, tipos de impacto,
     * los ocho estados, y los roles con sus permisos.
     * Se puede lanzar tantas veces como haga falta.
     */
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
