<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos del plazo del curso: fin = inicio del curso + 365 + días de los
 * movimientos (reinscripción 365, extensión de tiempo, ajuste de días,
 * mensualidad abierta). Las reinscripciones y ampliaciones ya capturadas en
 * las fichas de inscripción pasan a movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_term_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // Reinscripción: la ficha que la originó (se sincroniza con su fecha de reinscripción).
            $table->foreignId('student_enrollment_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('occurred_on');
            $table->unsignedSmallInteger('days');
            // Mensualidad abierta: fecha en que se cierra (sus días dejan de ser estimados).
            $table->date('closed_on')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'occurred_on']);
        });

        $now = now();

        DB::table('student_enrollments')->orderBy('id')->each(function (object $enrollment) use ($now): void {
            $base = ['school_id' => $enrollment->school_id, 'student_id' => $enrollment->student_id, 'created_at' => $now, 'updated_at' => $now];

            if ($enrollment->reenrolled_on !== null) {
                DB::table('student_term_movements')->insert($base + [
                    'student_enrollment_id' => $enrollment->id, 'type' => 'reenrollment', 'occurred_on' => $enrollment->reenrolled_on, 'days' => 365,
                ]);
            }

            if ($enrollment->includes_term_extension && $enrollment->extension_days > 0) {
                $occurredOn = $enrollment->reenrolled_on ?? $enrollment->registered_on
                    ?? DB::table('students')->where('id', $enrollment->student_id)->value('course_starts_on') ?? $now->toDateString();

                DB::table('student_term_movements')->insert($base + [
                    'type' => 'extension', 'occurred_on' => $occurredOn, 'days' => min(120, (int) $enrollment->extension_days),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_term_movements');
    }
};
