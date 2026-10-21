<?php

namespace App\Enums;

/**
 * Incidencias del horario del teacher. Todas quitan sus horas del panel,
 * excepto "Cubre horas", que le agrega las horas del teacher que cubre.
 * "Baja" y "Reingreso" cambian el estatus del teacher desde su fecha.
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
    case Termination = 'termination';
    case Rehire = 'rehire';

    public function label(): string
    {
        return __("availability.incident_types.{$this->value}");
    }

    public function isCoverage(): bool
    {
        return $this === self::Covers;
    }

    /** Baja o reingreso: cambian el estatus del teacher (un solo día, sin horas). */
    public function isStatusChange(): bool
    {
        return in_array($this, [self::Termination, self::Rehire], true);
    }

    /** Quita las horas del teacher en su periodo (permisos, vacaciones, faltas…). */
    public function isAbsence(): bool
    {
        return ! $this->isCoverage() && ! $this->isStatusChange();
    }

    public function badge(): string
    {
        return match ($this) {
            self::Covers, self::Rehire => 'success',
            self::Absence, self::Unforeseen, self::Termination => 'danger',
            self::SickLeave => 'info',
            default => 'warning',
        };
    }
}
