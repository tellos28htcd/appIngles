<?php

namespace App\Enums;

/**
 * Incidencias del horario del teacher. Todas quitan sus horas del panel,
 * excepto "Cubre horas", que le agrega las horas del teacher que cubre.
 */
enum TeacherIncidentType: string
{
    case PaidLeave = 'paid_leave';
    case UnpaidLeave = 'unpaid_leave';
    case Vacation = 'vacation';
    case SickLeave = 'sick_leave';
    case Absence = 'absence';
    case Unforeseen = 'unforeseen';
    case Covers = 'covers';

    public function label(): string
    {
        return __("availability.incident_types.{$this->value}");
    }

    public function isCoverage(): bool
    {
        return $this === self::Covers;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Covers => 'success',
            self::Absence, self::Unforeseen => 'danger',
            self::SickLeave => 'info',
            default => 'warning',
        };
    }
}
