<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teachers de cada escuela. Cada teacher tiene su usuario (rol Teacher) para
 * entrar al sistema; los datos de contratación y domicilio viven aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Datos personales
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('second_last_name', 80)->nullable();
            $table->char('curp', 18)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('photo_path')->nullable();

            // Domicilio
            $table->string('street', 150)->nullable();
            $table->string('exterior_number', 20)->nullable();
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->char('postal_code', 5)->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->restrictOnDelete();

            // Contratación: tope de horas por semana (48 tiempo completo, 24 medio tiempo, asignadas en hora clase).
            $table->string('contract_type', 20)->nullable();
            $table->unsignedTinyInteger('weekly_hours')->nullable();

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['school_id', 'last_name']);
            $table->unique(['school_id', 'curp']);
            $table->unique(['school_id', 'rfc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
