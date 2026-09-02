<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los tipos de impacto pasan de la jerga de calidad a lenguaje llano.
 *
 * «Plazo» no le dice nada a quien trabaja en el muelle; «Menos tiempo de
 * espera», sí. Se renombran las filas existentes en vez de crear otras nuevas
 * para no romper las propuestas que ya apuntaban a ellas.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $nuevos = [
        'Calidad' => 'Menos errores',
        'Coste' => 'Menos coste',
        'Plazo' => 'Menos tiempo de espera',
        'Cliente' => 'Mejor para el cliente',
        'Riesgo' => 'Menos riesgo',
    ];

    public function up(): void
    {
        foreach ($this->nuevos as $antiguo => $nuevo) {
            DB::table('impacts')->where('name', $antiguo)->update(['name' => $nuevo]);
        }
    }

    public function down(): void
    {
        foreach ($this->nuevos as $antiguo => $nuevo) {
            DB::table('impacts')->where('name', $nuevo)->update(['name' => $antiguo]);
        }
    }
};
