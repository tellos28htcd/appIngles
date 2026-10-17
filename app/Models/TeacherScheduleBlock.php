<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hora del horario base del teacher: día de la semana (1 = lunes … 7 = domingo) y bloque del catálogo. */
#[Fillable(['school_id', 'teacher_id', 'weekday', 'schedule_slot_id'])]
class TeacherScheduleBlock extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<ScheduleSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }
}
