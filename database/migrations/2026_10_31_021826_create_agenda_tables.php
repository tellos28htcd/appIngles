<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda (fase 1):
 * - Mi Instituto → Operación: cupo por club, cupo por aula virtual y aulas virtuales por horario.
 * - bookings: reservación de una hora (asesoría o club, presencial o en línea) con el aula que el
 *   sistema asigna, lista de espera, la actividad que le toca (LLA) y su estado (reservada,
 *   asistió, cancelada con motivo).
 * - booking_events: historial de todo movimiento de la reservación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedSmallInteger('club_capacity')->nullable()->after('session_capacity');
            $table->unsignedSmallInteger('virtual_room_capacity')->nullable()->after('club_capacity');
            $table->unsignedTinyInteger('virtual_rooms_per_slot')->default(0)->after('virtual_room_capacity');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('schedule_slot_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('modality', 10);
            // Asesoría presencial: la hora del panel (teacher + aula). Club: el club programado.
            $table->foreignId('teacher_availability_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('virtual_room')->nullable();
            $table->boolean('is_waitlist')->default(false);
            $table->string('status', 12)->default('booked');
            $table->string('cancellation_reason', 20)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Actividad que le toca (LLA) según su avance.
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('activity_number')->nullable();
            $table->boolean('booked_by_reception')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'date', 'schedule_slot_id']);
            $table->index(['student_id', 'date']);
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->string('detail', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('bookings');
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn(['club_capacity', 'virtual_room_capacity', 'virtual_rooms_per_slot']));
    }
};
