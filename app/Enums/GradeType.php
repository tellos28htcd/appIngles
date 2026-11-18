<?php

namespace App\Enums;

/**
 * Tipo de calificación del Kardex: la firma (F) se usa en las actividades 1–3 y en los clubs;
 * la calificación (A, B, C) en las actividades 4–7. Cada actividad del catálogo dice cuál lleva.
 */
enum GradeType: string
{
    case Signature = 'signature';
    case Score = 'score';

    public function label(): string
    {
        return __("kardex.grade_types.{$this->value}");
    }

    /** Tipo por omisión según el número de actividad (1–3 firma; 4 en adelante calificación). */
    public static function forActivityNumber(int $number): self
    {
        return $number <= 3 ? self::Signature : self::Score;
    }
}
