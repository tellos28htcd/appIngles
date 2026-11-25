<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Navigation;

/**
 * Agenda: la consulta quien tenga la opción en el menú; agendan, cancelan y marcan asistencia
 * todos ellos salvo los teachers. El alumno reserva y cancela las suyas desde "Mi agenda".
 */
class BookingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'agenda.index');
    }

    public function manage(User $actor): bool
    {
        return $this->viewAny($actor) && $actor->loadMissing('role')->role?->slug !== Role::TEACHER;
    }

    /**
     * Cambiar presencial ↔ en línea (o la actividad) de una reservación ya hecha, p. ej. cuando el alumno o
     * recepción eligieron por error: solo Recepción y el Administrador de la escuela.
     */
    public function changeActivity(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug, [Role::SCHOOL_ADMIN, Role::RECEPTION], true);
    }

    /** Agendar con la clase ya empezada (hasta que termine): solo Recepción y el Administrador de la escuela. */
    public function bookDuringClass(User $actor): bool
    {
        return $this->changeActivity($actor);
    }

    /** Pesos newton y carga de cada aula en el listado del día: solo Administrador de escuela y Recepción. */
    public function viewWeights(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug, [Role::SCHOOL_ADMIN, Role::RECEPTION], true);
    }

    /** Lo programado en Horas Coordinador dentro de la agenda: Administrador, Recepción, Teacher y Coordinador (nunca el alumno). */
    public function viewCoordination(User $actor): bool
    {
        return $this->viewAny($actor) && in_array($actor->loadMissing('role')->role?->slug,
            [Role::SCHOOL_ADMIN, Role::RECEPTION, Role::TEACHER, Role::ACADEMIC_COORDINATOR], true);
    }

    /** "Mi agenda": el alumno ligado a su usuario. */
    public function bookOwn(User $actor): bool
    {
        return $actor->school_id !== null && $actor->loadMissing('role')->role?->slug === Role::STUDENT
            && Navigation::allows($actor, 'my-agenda.index');
    }

    public function cancelOwn(User $actor, Booking $booking): bool
    {
        return $this->bookOwn($actor) && $booking->status->isActive()
            && Student::withoutGlobalScope('school')->whereKey($booking->student_id)->where('user_id', $actor->id)->exists();
    }
}
