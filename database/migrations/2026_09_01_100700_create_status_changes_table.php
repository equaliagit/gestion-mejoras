<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial. Se escribe una fila por cada cambio de estado y no se edita
 * ni se borra nunca. De aquí salen el historial de la ficha, los avisos y
 * todos los datos de la pantalla de informes.
 *
 * Sin `updated_at` a propósito: si no se puede modificar, no hay nada que fechar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('proposal_statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('proposal_statuses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['proposal_id', 'created_at']);
            $table->index(['to_status_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_changes');
    }
};
