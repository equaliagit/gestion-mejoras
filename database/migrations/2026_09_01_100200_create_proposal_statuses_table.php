<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los ocho estados del flujo. El `code` es el nombre interno y no se toca:
 * las reglas de transición y los avisos cuelgan de él. El `name` sí se puede
 * cambiar desde el panel, es lo que ve el usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->string('color', 20)->default('gray');
            $table->unsignedSmallInteger('position')->default(0);

            // Cuenta como pendiente en los contadores del comité.
            $table->boolean('is_open')->default(true);

            // Exige motivo escrito al pasar a este estado (rechazada, aplazada).
            $table->boolean('requires_reason')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_statuses');
    }
};
