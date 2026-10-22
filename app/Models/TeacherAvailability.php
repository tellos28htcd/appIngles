<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Celda del panel: el teacher está disponible esa fecha, en ese bloque de
 * horario y (cuando ya se asignó) en esa aula. Si cubre a otro teacher, lo indica.
 */
#[Fillable(['school_id', 'teacher_id', 'date', 'schedule_slot_id', 'classroom_id', 'is_absent', 'held_classroom_id', 'covered_teacher_id', 'teacher_incident_id', 'club_session_id'])]
class TeacherAvailability extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_absent' => 'boolean',
        ];
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<ScheduleSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }

    /** @return BelongsTo<ClubSession, $this> */
    public function clubSession(): BelongsTo
    {
        return $this->belongsTo(ClubSession::class);
    }
}
