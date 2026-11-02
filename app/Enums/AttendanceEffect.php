<?php

namespace App\Enums;

/** Cómo cuenta un motivo de cancelación para el índice de asistencia del alumno. */
enum AttendanceEffect: string
{
    case Attended = 'attended';
    case Absent = 'absent';
    case None = 'none';

    public function label(): string
    {
        return __("catalogs.attendance_effects.{$this->value}");
    }

    /** true = asistió, false = faltó, null = no cuenta. */
    public function attendance(): ?bool
    {
        return match ($this) {
            self::Attended => true,
            self::Absent => false,
            self::None => null,
        };
    }
}
