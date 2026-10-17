<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;

/**
 * Panel de disponibilidad de horarios: lo consulta quien tenga la opción en
 * el menú; lo editan (armar semana, asignar aulas) el administrador de la
 * escuela y el coordinador académico.
 */
class AvailabilityWeekPolicy
{
    /** @var list<string> */
    public const EDITOR_ROLES = [Role::SCHOOL_ADMIN, Role::ACADEMIC_COORDINATOR];

    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'availability.index');
    }

    public function manage(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug, self::EDITOR_ROLES, true);
    }
}
