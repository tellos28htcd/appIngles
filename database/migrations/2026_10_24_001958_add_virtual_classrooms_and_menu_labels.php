<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Aulas: "aula virtual" (sin marcar = física). Los clubs en línea usan aulas virtuales
 *   para clubs; los presenciales, aulas audiovisuales físicas.
 * - Menú: "Catálogos de alumnos" con su acento y en plural.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('is_virtual')->default(false)->after('is_club_room');
        });

        DB::table('menu_items')->where('slug', 'settings-student-catalogs')
            ->whereIn('label', ['Catalogo de alumnos', 'Catálogo de alumnos', 'Catalogos de alumnos'])
            ->update(['label' => 'Catálogos de alumnos']);
    }

    public function down(): void
    {
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('is_virtual'));
    }
};
