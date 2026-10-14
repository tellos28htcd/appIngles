<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
    case Reassigned = 'reassigned';

    public function label(): string
    {
        return __("students.status.{$this->value}");
    }

    /** Solo el alumno activo o en pausa entra al sistema. */
    public function canLogIn(): bool
    {
        return in_array($this, [self::Active, self::Paused], true);
    }

    /** Baja y pausa piden motivo; queda en el histórico. */
    public function requiresReason(): bool
    {
        return in_array($this, [self::Withdrawn, self::Paused, self::Reassigned], true);
    }

    /** Variante semántica del badge (nunca colores de marca). */
    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Finished, self::Graduated => 'info',
            self::Withdrawn, self::Reassigned => 'neutral',
        };
    }
}
