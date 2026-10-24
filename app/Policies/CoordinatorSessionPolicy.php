<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;

/** Horas Coordinador: la ven y editan el coordinador académico y el administrador del instituto. */
class CoordinatorSessionPolicy
{
    /** @var list<string> */
    public const EDITOR_ROLES = [Role::SCHOOL_ADMIN, Role::ACADEMIC_COORDINATOR];

    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'coordinator.schedule');
    }

    public function manage(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug, self::EDITOR_ROLES, true);
    }
}
