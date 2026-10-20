<?php

namespace App\Enums;

/**
 * Movimientos del plazo del curso. Cada uno suma días al fin del curso:
 * fin = inicio del curso + 365 + días de todos los movimientos.
 */
enum StudentTermMovementType: string
{
    /** Se registra solo desde la ficha de inscripción (fecha de reinscripción). */
    case Reenrollment = 'reenrollment';
    case Extension = 'extension';
    case Adjustment = 'adjustment';
    /** Mensualidad abierta: días estimados hasta que se cierra con la fecha real. */
    case OpenMonthly = 'open_monthly';

    public function label(): string
    {
        return __("students.term.types.{$this->value}");
    }

    /** Máximo de días por movimiento. */
    public function maxDays(): int
    {
        return match ($this) {
            self::Reenrollment => 365,
            self::Extension => 120,
            self::Adjustment => 365,
            self::OpenMonthly => 730,
        };
    }

    /** ¿Se captura a mano en el expediente? (La reinscripción viene de la ficha). */
    public function isManual(): bool
    {
        return $this !== self::Reenrollment;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Reenrollment => 'success',
            self::Extension => 'info',
            self::Adjustment => 'neutral',
            self::OpenMonthly => 'warning',
        };
    }

    /** @return array<string, string> tipos que se capturan a mano */
    public static function manualOptions(): array
    {
        return collect(self::cases())->filter->isManual()->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
