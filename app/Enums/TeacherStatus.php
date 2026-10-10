<?php

namespace App\Enums;

enum TeacherStatus: string
{
    case Active = 'active';
    case Leave = 'leave';
    case PaidLeave = 'paid_leave';
    case UnpaidLeave = 'unpaid_leave';
    case Terminated = 'terminated';

    public function label(): string
    {
        return __("teachers.status.{$this->value}");
    }

    /** Solo un teacher activo inicia sesión y recibe clases. */
    public function canWork(): bool
    {
        return $this === self::Active;
    }

    /** Variante semántica del badge (nunca colores de marca). */
    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Leave, self::PaidLeave, self::UnpaidLeave => 'warning',
            self::Terminated => 'neutral',
        };
    }
}
