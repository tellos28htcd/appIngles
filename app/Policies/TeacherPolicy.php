<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use App\Support\Navigation;

/**
 * Quién administra teachers lo decide Roles y permisos (opción "Teachers" del
 * menú). Además, un usuario de escuela solo toca teachers de su escuela.
 */
class TeacherPolicy
{
    public function viewAny(User $actor): bool
    {
        return Navigation::allows($actor, 'teachers.index');
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, Teacher $teacher): bool
    {
        return $this->viewAny($actor)
            && ($actor->isPlatformAdmin() || $actor->school_id === $teacher->school_id);
    }

    public function viewPhoto(User $actor, Teacher $teacher): bool
    {
        return $this->update($actor, $teacher) || $actor->is($teacher->user);
    }

    public function delete(User $actor, Teacher $teacher): bool
    {
        return $this->update($actor, $teacher)
            && ! $actor->is($teacher->user)
            && $teacher->canBeDeleted();
    }

    public function resendInvitation(User $actor, Teacher $teacher): bool
    {
        return $this->update($actor, $teacher)
            && $teacher->status->canWork()
            && ! $teacher->user->hasPassword();
    }
}
