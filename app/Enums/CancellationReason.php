<?php

namespace App\Enums;

/**
 * Motivos de cancelación de una reservación. Para el índice de asistencia:
 * "No se presentó" cuenta como falta; "No terminó la actividad" y "Asistió,
 * decidió cancelar" como asistencia; "Avisó para cancelar" no cuenta.
 */
enum CancellationReason: string
{
    case NoShow = 'no_show';
    case Notice = 'notice';
    case Unfinished = 'unfinished';
    case AttendedCancelled = 'attended_cancelled';

    public function label(): string
    {
        return __("agenda.reasons.{$this->value}");
    }

    /** true = asistió, false = faltó, null = no cuenta. */
    public function attendance(): ?bool
    {
        return match ($this) {
            self::NoShow => false,
            self::Notice => null,
            self::Unfinished, self::AttendedCancelled => true,
        };
    }
}
