<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pesos de cada actividad por tipo de lección (balanceo de aulas en la agenda).
 * Vacío = la actividad no se hace en ese tipo de lección (la ruta de avance la salta).
 * "Uno a uno" (PR, presentación): el teacher no recibe a nadie más a esa hora.
 */
return new class extends Migration
{
    /** Pesos iniciales por número de actividad: [Lesson, Check Point, Verb in context, uno a uno]. */
    private const WEIGHTS = [
        1 => [5, 5, null, false],      // BA
        2 => [10, null, null, false],  // 1P
        3 => [40, 40, null, false],    // M
        4 => [40, null, null, false],  // WS
        5 => [15, null, null, false],  // 2P
        6 => [40, 40, 40, false],      // WP
        7 => [20, null, 20, false],    // SP
        8 => [null, 240, null, true],  // PR
    ];

    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedSmallInteger('weight_lesson')->nullable()->after('minutes');
            $table->unsignedSmallInteger('weight_checkpoint')->nullable()->after('weight_lesson');
            $table->unsignedSmallInteger('weight_verb_in_context')->nullable()->after('weight_checkpoint');
            $table->boolean('is_one_to_one')->default(false)->after('weight_verb_in_context');
        });

        // Base de Plataforma y copias de las escuelas (por número de actividad).
        foreach (self::WEIGHTS as $number => [$lesson, $checkpoint, $verb, $oneToOne]) {
            DB::table('activities')->where('number', $number)->update([
                'weight_lesson' => $lesson,
                'weight_checkpoint' => $checkpoint,
                'weight_verb_in_context' => $verb,
                'is_one_to_one' => $oneToOne,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['weight_lesson', 'weight_checkpoint', 'weight_verb_in_context', 'is_one_to_one']);
        });
    }
};
