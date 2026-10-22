<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programación mensual de clubs:
 * - Clubes: abreviaturas (p. ej. C12 = niveles 1 y 2) con su color.
 * - Aulas: "aula audiovisual (para clubs)" y periodos en que no se puede usar.
 * - club_sessions: club (abreviatura) + teacher + fecha + hora + aula + presencial/en línea.
 * - teacher_availabilities.club_session_id: esa hora del teacher es de club (no de clase normal).
 */
return new class extends Migration
{
    /** Abreviaturas iniciales por nombre del club: [abreviatura, niveles, color]. */
    private const CODES = [
        'conversation' => [['C12', [1, 2], 1], ['C23', [2, 3], 2]],
        'movie' => [['M12', [1, 2], 3], ['M23', [2, 3], 4]],
        'soundbooth' => [['S12', [1, 2], 5], ['S23', [2, 3], 6]],
        'book' => [['B12', [1, 2], 7], ['B23', [2, 3], 8]],
        'field' => [['F12', [1, 2], 9], ['F23', [2, 3], 1]],
        'ted' => [['T3', [3], 2]],
    ];

    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->json('codes')->nullable()->after('description');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('is_club_room')->default(false)->after('description');
        });

        Schema::create('classroom_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // Sin horas = todo el día.
            $table->foreignId('from_slot_id')->nullable()->constrained('schedule_slots')->nullOnDelete();
            $table->foreignId('to_slot_id')->nullable()->constrained('schedule_slots')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['classroom_id', 'starts_on', 'ends_on']);
        });

        Schema::create('club_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('schedule_slot_id')->constrained()->restrictOnDelete();
            $table->string('modality', 10);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Un aula y un teacher, un club a la vez.
            $table->unique(['classroom_id', 'date', 'schedule_slot_id']);
            $table->unique(['teacher_id', 'date', 'schedule_slot_id']);
            $table->index(['school_id', 'date']);
        });

        Schema::table('teacher_availabilities', function (Blueprint $table) {
            $table->foreignId('club_session_id')->nullable()->after('teacher_incident_id')->constrained()->nullOnDelete();
        });

        // Abreviaturas iniciales en los clubes existentes (catálogo base y escuelas), por su nombre.
        DB::table('clubs')->whereNull('codes')->orderBy('id')->each(function (object $club): void {
            $name = mb_strtolower($club->name);

            foreach (self::CODES as $key => $codes) {
                if (str_contains($name, $key)) {
                    DB::table('clubs')->where('id', $club->id)->update(['codes' => json_encode(array_map(
                        fn (array $code) => ['code' => $code[0], 'levels' => $code[1], 'color' => $code[2], 'is_active' => true], $codes,
                    ))]);

                    return;
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_availabilities', fn (Blueprint $table) => $table->dropConstrainedForeignId('club_session_id'));
        Schema::dropIfExists('club_sessions');
        Schema::dropIfExists('classroom_closures');
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('is_club_room'));
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn('codes'));
    }
};
