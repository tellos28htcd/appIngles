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
