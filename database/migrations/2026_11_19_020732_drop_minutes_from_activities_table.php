<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de Actividades ya no tiene "Minutos". "Catálogos de alumnos" se llama ahora "Parámetros comerciales"
 * (Motivos de cancelación, Calificaciones y Motivos del Kardex pasan a Catálogos académicos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('minutes');
        });

        DB::table('menu_items')->where('slug', 'settings-student-catalogs')->where('label', 'Catálogos de alumnos')
            ->update(['label' => 'Parámetros comerciales', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutes')->nullable()->after('description');
        });

        DB::table('menu_items')->where('slug', 'settings-student-catalogs')->where('label', 'Parámetros comerciales')
            ->update(['label' => 'Catálogos de alumnos', 'updated_at' => now()]);
    }
};
