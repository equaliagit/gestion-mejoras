<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivos de apoyo. Se guardan fuera de la carpeta pública, con nombre
 * aleatorio, y se sirven solo a quien tiene permiso para ver la propuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 120);
            $table->unsignedInteger('size_bytes');
            $table->timestamp('created_at')->useCurrent();

            $table->index('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
