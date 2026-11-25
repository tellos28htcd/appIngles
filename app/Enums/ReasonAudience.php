<?php

namespace App\Enums;

use App\Models\Role;
use App\Models\User;

/**
 * Quién usa un motivo del Kardex al calificar: el teacher, el personal (Recepción, Administración y Coordinación
 * académica) o ambos. Recepción ve los dos: los del teacher y los del personal.
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
     * Motivos que ve ese usuario: el teacher los suyos; Recepción los del teacher y los del personal; Administración y
     * Coordinación académica los del personal; "ambos" los ven todos.
     *
     * @return list<string>
     */
    public static function valuesFor(User $user): array
    {
        return match ($user->loadMissing('role')->role?->slug) {
            Role::TEACHER => [self::All->value, self::Teacher->value],
            Role::RECEPTION => [self::All->value, self::Teacher->value, self::Staff->value],
            default => [self::All->value, self::Staff->value],
        };
    }
}
