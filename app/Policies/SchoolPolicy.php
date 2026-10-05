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

    public function toggleStatus(User $actor, School $school): bool
    {
        return $actor->isPlatformAdmin();
    }
}
