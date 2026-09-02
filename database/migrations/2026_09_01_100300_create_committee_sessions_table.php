<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones del comité. Una propuesta entra en el orden del día de una sesión;
 * al cerrarla quedan registradas las decisiones de ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('held_on');
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('held_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_sessions');
    }
};
