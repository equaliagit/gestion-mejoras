<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La propuesta. Guarda lo que escribió el proponente y el estado de HOY;
 * cómo se llegó hasta ese estado vive en status_changes.
 *
 * `reference` y `submitted_at` van vacíos mientras es un borrador: el número
 * se asigna al enviar, para no dejar huecos por borradores abandonados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->nullable()->unique();

            // Proponente. Se guarda siempre, también en las anónimas:
            // lo que cambia es que la aplicación nunca lo muestra.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();

            $table->string('title', 140);
            $table->text('problem');
            $table->text('proposal');
            $table->text('expected_benefit');

            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('committee_priority', ['low', 'medium', 'high'])->nullable();

            $table->enum('visibility', ['public', 'private', 'anonymous'])->default('public');

            $table->foreignId('status_id')->constrained('proposal_statuses')->restrictOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('implementer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('committee_session_id')->nullable()->constrained()->nullOnDelete();

            $table->date('decided_at')->nullable();
            $table->text('decision_reason')->nullable();

            // Solo en aplazadas: cuándo se vuelve a mirar.
            $table->date('revisit_on')->nullable();

            $table->date('planned_start_on')->nullable();
            $table->date('planned_end_on')->nullable();
            $table->date('started_on')->nullable();
            $table->date('closed_on')->nullable();

            $table->text('result_summary')->nullable();

            // Vacío = borrador: solo lo ve su autor.
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status_id');
            $table->index('area_id');
            $table->index('user_id');
            $table->index(['visibility', 'status_id']);
            $table->index('submitted_at');
            $table->index('revisit_on');
            $table->index('planned_end_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
