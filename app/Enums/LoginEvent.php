<?php

namespace App\Enums;

enum LoginEvent: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Inactive = 'inactive';
    case Locked = 'locked';
    case Logout = 'logout';

    public function label(): string
    {
        return __('audit.login_events.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Succeeded => 'success',
            self::Logout => 'neutral',
            self::Failed, self::Inactive => 'warning',
            self::Locked => 'danger',
        };
    }
}
