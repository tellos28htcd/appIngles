<?php

namespace App\Policies;

use App\Models\AvailabilityWeek;
use App\Models\User;
use App\Support\Navigation;

/**
 * Programación de clubs: la consulta quien tenga la opción en el menú; la edita
 * quien edita el panel de disponibilidad (administrador de escuela y coordinador académico).
 */
class ClubSessionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'clubs.schedule');
    }

    public function manage(User $actor): bool
    {
        return $this->viewAny($actor) && $actor->can('manage', AvailabilityWeek::class);
    }
}
