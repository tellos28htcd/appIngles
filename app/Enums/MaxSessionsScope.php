<?php

namespace App\Enums;

enum MaxSessionsScope: string
{
    case School = 'school';
    case Student = 'student';

    public function label(): string
    {
        return __("schools.max_sessions_scope.{$this->value}");
    }
}
