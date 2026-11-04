<?php

use App\Actions\Agenda\AgendaService;
use App\Models\Booking;
use App\Models\ScheduleSlot;
use App\Models\School;
use Illuminate\Database\Migrations\Migration;

/**
 * Reservaciones en aula virtual que quedaron sin teacher: se les asigna el teacher del panel en esa
 * aula o uno de los que dan clase presencial a esa hora (como hace ahora la agenda al reservar).
 */
return new class extends Migration
{
    public function up(): void
    {
        $agenda = app(AgendaService::class);

        Booking::withoutGlobalScope('school')->whereNull('teacher_id')->whereNotNull('classroom_id')->whereNull('teacher_availability_id')
            ->where('kind', 'session')->where('modality', 'online')->where('status', '!=', 'cancelled')
            ->orderBy('id')->each(function (Booking $booking) use ($agenda): void {
                $school = School::find($booking->school_id);
                $slot = ScheduleSlot::withoutGlobalScope('school')->find($booking->schedule_slot_id);

                if ($school && $slot) {
                    $booking->teacher_id = $agenda->virtualTeacherId($school, $booking->date, $slot, $booking->classroom_id);
                    $booking->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Sin reversa: el teacher asignado es un dato válido.
    }
};
