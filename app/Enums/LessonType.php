<?php

namespace App\Enums;

enum LessonType: string
{
    case Lesson = 'lesson';
    case CheckPoint = 'checkpoint';
    case VerbInContext = 'verb_in_context';

    public function label(): string
    {
        return __("catalogs.lesson_types.{$this->value}");
    }
}
