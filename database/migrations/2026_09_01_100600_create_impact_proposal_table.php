<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué impactos ha marcado cada propuesta. Se puede marcar más de uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_proposal', function (Blueprint $table) {
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('impact_id')->constrained()->restrictOnDelete();

            $table->primary(['proposal_id', 'impact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_proposal');
    }
};
