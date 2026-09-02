<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Los catálogos con los que arranca la aplicación.
 *
 * Todo esto es editable después desde el panel; esto es solo el punto de
 * partida. Se usa updateOrInsert sobre la clave natural para poder volver a
 * lanzarlo sin duplicar nada.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $areas = [
            'Comercial', 'Operaciones', 'RRHH', 'Sistemas',
            'Desarrollo', 'Calidad', 'Compras', 'Logística',
        ];

        foreach ($areas as $i => $name) {
            DB::table('areas')->updateOrInsert(
                ['name' => $name],
                ['position' => ($i + 1) * 10, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $impacts = ['Calidad', 'Coste', 'Plazo', 'Cliente', 'Riesgo'];

        foreach ($impacts as $i => $name) {
            DB::table('impacts')->updateOrInsert(
                ['name' => $name],
                ['position' => ($i + 1) * 10, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        // Los ocho estados del flujo. El código no se toca: las reglas de
        // transición y los avisos cuelgan de él. El nombre sí es editable.
        $statuses = [
            ['code' => 'new',           'name' => 'Nueva',           'color' => 'blue',   'is_open' => true,  'requires_reason' => false],
            ['code' => 'in_review',     'name' => 'En revisión',     'color' => 'amber',  'is_open' => true,  'requires_reason' => false],
            ['code' => 'awaiting_info', 'name' => 'Espera info',     'color' => 'orange', 'is_open' => true,  'requires_reason' => false],
            ['code' => 'in_committee',  'name' => 'En comité',       'color' => 'teal',   'is_open' => true,  'requires_reason' => false],
            ['code' => 'approved',      'name' => 'Aprobada',        'color' => 'green',  'is_open' => true,  'requires_reason' => false],
            ['code' => 'rejected',      'name' => 'Rechazada',       'color' => 'red',    'is_open' => false, 'requires_reason' => true],
            ['code' => 'postponed',     'name' => 'Aplazada',        'color' => 'gray',   'is_open' => false, 'requires_reason' => true],
            ['code' => 'implemented',   'name' => 'Implantada',      'color' => 'green',  'is_open' => false, 'requires_reason' => false],
        ];

        foreach ($statuses as $i => $status) {
            DB::table('proposal_statuses')->updateOrInsert(
                ['code' => $status['code']],
                $status + ['position' => ($i + 1) * 10, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }
}
