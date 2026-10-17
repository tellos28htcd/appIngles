<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horario base de cada teacher, incidencias y panel semanal de disponibilidad
 * (teacher × día × hora → aula). Las semanas generadas quedan como histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Número (orden de la columna en el panel) y nombre corto del teacher; Lista de espera.
        // (Con verificaciones: un primer intento quedó a medias por un nombre de índice muy largo.)
        if (! Schema::hasColumn('teachers', 'short_name')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->string('short_name', 20)->nullable()->after('second_last_name');
                $table->unsignedTinyInteger('panel_number')->nullable()->after('short_name');
                $table->boolean('is_waitlist')->default(false)->after('panel_number');

                $table->unique(['school_id', 'panel_number']);
            });
        }

        Schema::dropIfExists('teacher_schedule_blocks');

        // Horario base: qué horas del catálogo trabaja cada día de la semana (1 = lunes … 7 = domingo).
        Schema::create('teacher_schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->foreignId('schedule_slot_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'weekday', 'schedule_slot_id'], 'teacher_schedule_blocks_unique');
        });

        Schema::create('teacher_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            // Sin horas = el día completo.
            $table->foreignId('from_slot_id')->nullable()->constrained('schedule_slots')->restrictOnDelete();
            $table->foreignId('to_slot_id')->nullable()->constrained('schedule_slots')->restrictOnDelete();
            // Solo "Cubre horas": a quién cubre.
            $table->foreignId('covered_teacher_id')->nullable()->constrained('teachers')->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'starts_on', 'ends_on']);
        });

        // Semanas ya armadas (lunes de cada semana): histórico del panel.
        Schema::create('availability_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'starts_on']);
        });

        // Celdas del panel: el teacher está disponible ese día a esa hora, en esa aula.
        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('schedule_slot_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            // Si cubre horas de otro teacher.
            $table->foreignId('covered_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('teacher_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'date', 'schedule_slot_id'], 'teacher_availabilities_cell_unique');
            // RN-05: un aula no tiene dos teachers a la misma hora.
            $table->unique(['school_id', 'date', 'schedule_slot_id', 'classroom_id'], 'teacher_availabilities_classroom_unique');
            $table->index(['school_id', 'date']);
        });

        // El catálogo "Salones" ahora se llama "Aulas" (solo si nadie le cambió el nombre en pantalla).
        DB::table('menu_items')->where('slug', 'settings-classrooms')->where('label', 'Salones')->update(['label' => 'Aulas']);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('slug', 'settings-classrooms')->where('label', 'Aulas')->update(['label' => 'Salones']);

        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('availability_weeks');
        Schema::dropIfExists('teacher_incidents');
        Schema::dropIfExists('teacher_schedule_blocks');

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'panel_number']);
            $table->dropColumn(['short_name', 'panel_number', 'is_waitlist']);
        });
    }
};
