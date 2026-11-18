<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pesos newton del Excel del instituto (minutos que tarda el teacher en revisar cada actividad), en la base y en
 * todas las escuelas: Lesson BA 4, 1P 7, M 10, WS 9, 2P 15, WP 11, SP 18; Check Point BA 6, M 11, WP 180, SP 50;
 * Verb in context M 7, WP 6, SP 9.
 */
return new class extends Migration
{
    private const WEIGHTS = [
        'lesson' => ['1' => 4, '2' => 7, '3' => 10, '4' => 9, '5' => 15, '6' => 11, '7' => 18],
        'checkpoint' => ['1' => 6, '3' => 11, '6' => 180, '7' => 50],
        'verb_in_context' => ['3' => 7, '6' => 6, '7' => 9],
    ];

    public function up(): void
    {
        foreach (self::WEIGHTS as $type => $weights) {
            DB::table('lessons')->where('type', $type)->update(['activity_weights' => json_encode($weights)]);
        }
    }

    public function down(): void
    {
        // Los pesos anteriores quedan en el respaldo de la base; no se restauran.
    }
};
