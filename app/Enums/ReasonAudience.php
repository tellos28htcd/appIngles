<?php

namespace App\Enums;

use App\Models\Role;
use App\Models\User;

/**
 * Quién usa un motivo del Kardex al calificar: el teacher, el personal (Recepción, Administración y Coordinación
 * académica) o ambos.
 */
enum ReasonAudience: string
{
    case All = 'all';
    case Teacher = 'teacher';
    case Staff = 'staff';

    public function label(): string
    {
        return __("kardex.reason_audiences.{$this->value}");
    }

    /**
     * Motivos que ve ese usuario: el teacher los suyos; el personal los del personal; ambos, los de todos.
     *
     * @return list<string>
     */
    public static function valuesFor(User $user): array
    {
        $own = $user->loadMissing('role')->role?->slug === Role::TEACHER ? self::Teacher : self::Staff;

        return [self::All->value, $own->value];
    }
}
