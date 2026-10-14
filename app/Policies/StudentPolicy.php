<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Navigation;

/**
 * Quién ve expedientes lo decide Roles y permisos (opción "Expedientes").
 * Solo el administrador de la escuela y Servicios escolares dan de alta,
 * editan, cambian estatus, importan y envían el acceso. Siempre de su escuela.
 */
class StudentPolicy
{
    /** @var list<string> */
    public const MANAGER_ROLES = [Role::SCHOOL_ADMIN, Role::STUDENT_SERVICES];

    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'students.index');
    }

    public function view(User $actor, Student $student): bool
    {
        return $this->viewAny($actor) && $actor->school_id === $student->school_id;
    }

    /** La foto la ve quien consulta el expediente y el propio alumno. */
    public function viewPhoto(User $actor, Student $student): bool
    {
        return $this->view($actor, $student) || ($student->user_id !== null && $actor->id === $student->user_id);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug, self::MANAGER_ROLES, true);
    }

    public function update(User $actor, Student $student): bool
    {
        return $this->create($actor) && $actor->school_id === $student->school_id;
    }

    public function sendAccess(User $actor, Student $student): bool
    {
        return $this->update($actor, $student) && $student->canReceiveAccess();
    }

    public function delete(User $actor, Student $student): bool
    {
        return $this->update($actor, $student) && $student->canBeDeleted();
    }
}
