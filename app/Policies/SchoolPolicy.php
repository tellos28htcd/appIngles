<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/** Solo el Super Administrador da de alta y administra escuelas (RN-18). */
class SchoolPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, School $school): bool
    {
        return $actor->isPlatformAdmin();
    }

    /** "Mi escuela": el administrador de la escuela edita sus propios datos. */
    public function updateOwn(User $actor, School $school): bool
    {
        return $actor->isSchoolAdmin() && $actor->school_id === $school->id;
    }

    public function toggleStatus(User $actor, School $school): bool
    {
        return $actor->isPlatformAdmin();
    }
}
