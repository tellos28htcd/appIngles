<?php

namespace App\Enums;

enum SelfBooking: string
{
    case Disabled = 'disabled';
    case AtLeast24HoursBefore = 'at_least_24h_before';
    case AnyTime = 'any_time';

    public function label(): string
    {
        return __("schools.self_booking.{$this->value}");
    }
}
