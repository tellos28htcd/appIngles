<?php

namespace App\Enums;

enum ContractType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Hourly = 'hourly';

    public const MAX_WEEKLY_HOURS = 48;

    /** Tope de horas por semana; en hora clase son las horas asignadas (máximo 48). */
    public function maxWeeklyHours(): int
    {
        return match ($this) {
            self::FullTime => 48,
            self::PartTime => 24,
            self::Hourly => self::MAX_WEEKLY_HOURS,
        };
    }

    public function hasFixedHours(): bool
    {
        return $this !== self::Hourly;
    }

    public function label(): string
    {
        return __("teachers.contract_types.{$this->value}");
    }
}
