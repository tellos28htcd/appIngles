<?php

namespace App\Enums;

enum LoginEvent: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Inactive = 'inactive';
    case Locked = 'locked';
    case Logout = 'logout';
}
