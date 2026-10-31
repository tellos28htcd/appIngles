<?php

namespace App\Enums;

/**
 * Quién reserva las sesiones: recepción siempre puede; el alumno según la opción:
 * "No permitir" = no; "Día anterior" = hasta el día anterior; "A cualquier hora" = sí.
 */
enum SelfBooking: string
{
    case Disabled = 'disabled';
    /** El valor guardado se conserva de la versión anterior ("Solo 24 h antes"). */
    case DayBefore = 'at_least_24h_before';
    case AnyTime = 'any_time';

    public function label(): string
    {
        return __("schools.self_booking.{$this->value}");
    }

    /** ¿El alumno puede reservar él mismo? */
    public function studentCanBook(): bool
    {
        return $this !== self::Disabled;
    }

    /** ¿Recepción puede agendar por el alumno? */
    public function receptionCanBook(): bool
    {
        return true;
    }
}
