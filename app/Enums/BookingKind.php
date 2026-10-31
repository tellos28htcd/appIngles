<?php

namespace App\Enums;

/** Qué se reserva: una asesoría (sesión con su actividad) o un club. */
enum BookingKind: string
{
    case Session = 'session';
    case Club = 'club';

    public function label(): string
    {
        return __("agenda.kinds.{$this->value}");
    }
}
