<?php

namespace App\Policies;

use App\Models\TeacherIncident;
use App\Models\User;
use App\Support\Navigation;

/**
 * Incidencias de teachers: consulta quien tenga la opción en el menú;
 * registran y editan el administrador de la escuela y el coordinador académico.
 */
class TeacherIncidentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'teacher-incidents.index');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug, AvailabilityWeekPolicy::EDITOR_ROLES, true);
    }

    public function update(User $actor, TeacherIncident $incident): bool
    {
        return $this->create($actor) && $actor->school_id === $incident->school_id;
    }
}
