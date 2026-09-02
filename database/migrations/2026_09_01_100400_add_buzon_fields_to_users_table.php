<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos propios del Buzón sobre la tabla de usuarios de Laravel.
 * `password` pasa a ser opcional: quien entra con Microsoft 365 no tiene ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->string('microsoft_id')->nullable()->unique()->after('password');
            $table->boolean('is_active')->default(true)->after('microsoft_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['microsoft_id', 'is_active', 'last_login_at']);
        });
    }
};
