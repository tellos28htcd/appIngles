<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Navigation;

/**
 * Kardex → Calificaciones: califican el Teacher (solo sus alumnos), Recepción, el Coordinador académico y el
 * Administrador de la escuela. Corregir o quitar una calificación: solo Coordinador académico y Administrador.
 * El alumno solo ve su propio Kardex ("Mi avance").
 */
class KardexEntryPolicy
{
    public const GRADERS = [Role::SCHOOL_ADMIN, Role::RECEPTION, Role::TEACHER, Role::ACADEMIC_COORDINATOR];

    public const CORRECTORS = [Role::SCHOOL_ADMIN, Role::ACADEMIC_COORDINATOR];

    public function grade(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'kardex.grades')
            && in_array($actor->loadMissing('role')->role?->slug, self::GRADERS, true);
    }

    /** La reservación es de la escuela y, si quien califica es teacher, es de uno de sus alumnos. */
    public function gradeBooking(User $actor, Booking $booking): bool
    {
        if (! $this->grade($actor) || $booking->school_id !== $actor->school_id) {
            return false;
        }

        $teacherId = $this->teacherIdOf($actor);

        return $actor->role->slug !== Role::TEACHER || ($teacherId !== null && $booking->teacher_id === $teacherId);
    }

    public function correct(User $actor): bool
    {
        return $this->grade($actor) && in_array($actor->role->slug, self::CORRECTORS, true);
    }

    public function viewOwn(User $actor): bool
    {
        return $actor->school_id !== null && $actor->loadMissing('role')->role?->slug === Role::STUDENT
            && Navigation::allows($actor, 'my-kardex.index');
    }

    /** Teacher ligado al usuario (solo para el rol Teacher; los demás ven a todos). */
    public function teacherIdOf(User $actor): ?int
    {
        return once(fn (): ?int => Teacher::withoutGlobalScope('school')->where('school_id', $actor->school_id)->where('user_id', $actor->id)->value('id'));
    }
}
