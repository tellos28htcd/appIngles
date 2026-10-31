<?php

namespace App\Enums;

/** Estado de una reservación (la lista de espera es aparte: is_waitlist). */
enum BookingStatus: string
{
    case Booked = 'booked';
    case Attended = 'attended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("agenda.statuses.{$this->value}");
    }

    /** Ocupa lugar y cuenta para el avance (no cancelada). */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }
}
