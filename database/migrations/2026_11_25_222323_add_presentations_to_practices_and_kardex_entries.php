<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presentaciones de la ruta: la actividad 7 de las lecciones 15 y 17 (Check Point C1 y C2) de cada libro es la
 * presentación del alumno (se marca en el catálogo de Prácticas). Se califica con A/B/C y puede quedar
 * "pendiente" en el Kardex (sin calificación) para que el alumno siga con las actividades siguientes.
 */
return new class extends Migration
{
    /** Lecciones cuya actividad 7 es presentación. */
    private const LESSON_NUMBERS = [15, 17];

    private const ACTIVITY_NUMBER = 7;

    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->boolean('is_presentation')->default(false)->after('name');
        });

        Schema::table('kardex_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('grade_id')->nullable()->change();
            $table->boolean('is_pending')->default(false)->after('grade_id');
        });

        DB::table('practices')->where('activity_number', self::ACTIVITY_NUMBER)
            ->whereIn('lesson_id', DB::table('lessons')->whereIn('number', self::LESSON_NUMBERS)->select('id'))
            ->update(['is_presentation' => true]);
    }

    public function down(): void
    {
        Schema::table('kardex_entries', function (Blueprint $table) {
            $table->dropColumn('is_pending');
        });

        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn('is_presentation');
        });
    }
};
