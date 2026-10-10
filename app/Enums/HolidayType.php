<?php

namespace App\Enums;

enum HolidayType: string
{
    /** Descanso obligatorio de la Ley Federal del Trabajo (art. 74), generado por año. */
    case Official = 'official';

    /** Día o periodo sin clase propio de la escuela. */
    case School = 'school';

    public function label(): string
    {
        return __("settings.holiday_types.{$this->value}");
    }
}
