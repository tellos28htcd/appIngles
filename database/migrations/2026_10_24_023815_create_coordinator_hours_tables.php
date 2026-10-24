<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horas Coordinador:
 * - coordinator_activities: catálogo (base y copia por escuela) con horas autorizadas al mes (HA).
 *   "Ocupa la hora del teacher" (p. ej. Revisión de Planeaciones): esa hora del teacher deja de estar
 *   disponible para clases y clubs y se marca en el panel con su letra (R).
 * - coordinator_sessions: actividad programada en una fecha y hora (con teacher solo si la ocupa).
 * - teacher_availabilities.coordinator_session_id: celda del panel tomada por la actividad.
 */
return new class extends Migration
{
    /** Actividades iniciales (versión de esta migración): [nombre, HA, ocupa la hora del teacher, letra en el panel]. */
    private const ACTIVITIES = [
        ['Evaluación de Club', 2, false, null],
        ['Programación de Clubs', 4, false, null],
        ['Elaboración de club en Canva', 4, false, null],
        ['Elaboración de clubs', 1, false, null],
        ['Preparación de junta Mensual', 2, false, null],
        ['Junta mensual', 1, false, null],
        ['Preparación de retroalimentación', 1, false, null],
        ['Retroalimentación', 3, false, null],
        ['Registro de resultados EA', 3, false, null],
        ['Registro de resultados REA', 2, false, null],
        ['Revisión de Planeaciones', 8, true, 'R'],
        ['Revisión de WP', 4, false, null],
        ['Registro de resultados WP', 2, false, null],
    ];

    public function up(): void
    {
        Schema::create('coordinator_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->nullable()->constrained('coordinator_activities')->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('authorized_hours', 5, 1)->default(0);
            $table->boolean('uses_teacher_hour')->default(false);
            $table->string('panel_mark', 3)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('coordinator_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coordinator_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('schedule_slot_id')->constrained()->restrictOnDelete();
            $table->boolean('repeats')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // El coordinador hace una actividad a la vez.
            $table->unique(['school_id', 'date', 'schedule_slot_id']);
            $table->index(['school_id', 'date']);
        });

        Schema::table('teacher_availabilities', function (Blueprint $table) {
            $table->foreignId('coordinator_session_id')->nullable()->after('club_session_id')->constrained()->nullOnDelete();
        });

        // Catálogo base y copia para las escuelas existentes.
        $now = now();
        $schools = DB::table('schools')->pluck('id');

        foreach (self::ACTIVITIES as $order => [$name, $hours, $usesTeacher, $mark]) {
            $row = ['name' => $name, 'authorized_hours' => $hours, 'uses_teacher_hour' => $usesTeacher, 'panel_mark' => $mark,
                'sort_order' => ($order + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            $baseId = DB::table('coordinator_activities')->insertGetId(['school_id' => null, 'base_id' => null, ...$row]);

            foreach ($schools as $schoolId) {
                DB::table('coordinator_activities')->insert(['school_id' => $schoolId, 'base_id' => $baseId, ...$row]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('teacher_availabilities', fn (Blueprint $table) => $table->dropConstrainedForeignId('coordinator_session_id'));
        Schema::dropIfExists('coordinator_sessions');
        Schema::dropIfExists('coordinator_activities');
    }
};
