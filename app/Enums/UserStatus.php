<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __("users.status.{$this->value}");
    }
}
