<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caso especial del alumno: máximo de horas de práctica por día. Si tiene número, reemplaza la regla de
 * 2 horas (o 1 con asistencia baja) y el máximo de prácticas por día de Mi Escuela; null = reglas normales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_daily_practices')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('max_daily_practices');
        });
    }
};
