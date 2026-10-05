<?php

namespace App\Enums;

enum FailedActivityPolicy: string
{
    case Delete = 'delete';
    case Shift = 'shift';

    public function label(): string
    {
        return __("schools.failed_activity_policy.{$this->value}");
    }
}
