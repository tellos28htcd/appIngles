<?php

namespace App\Enums;

/** Fecha de la ficha de inscripción desde la que la escuela cuenta el fin del curso. */
enum CourseEndFrom: string
{
    case Contract = 'contract';
    case MaterialDelivery = 'material';

    public function label(): string
    {
        return __("schools.course_end_from.{$this->value}");
    }
}
