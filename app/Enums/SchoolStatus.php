<?php

namespace App\Enums;

enum SchoolStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __("schools.status.{$this->value}");
    }
}
