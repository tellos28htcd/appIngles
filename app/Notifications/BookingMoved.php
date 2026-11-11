<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\ScheduleSlot;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Aviso al alumno: el balanceo de aulas lo cambió de aula y teacher (misma fecha y hora).
 * Aparece en su cuenta al entrar (ventana hasta que la marque como leída) y en Mi agenda ve el lugar nuevo.
 */
class BookingMoved extends Notification
{
    public function __construct(private readonly Booking $booking, private readonly ScheduleSlot $slot, private readonly string $teacher) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array{booking_id: int, date: string, hour: string, place: string, teacher: string} */
    public function toArray(User $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'date' => $this->booking->date->toDateString(),
            'hour' => $this->slot->range(),
            'place' => $this->booking->placeLabel(),
            'teacher' => $this->teacher,
        ];
    }
}
