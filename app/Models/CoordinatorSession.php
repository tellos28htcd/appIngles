<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hora programada del coordinador: actividad, fecha y hora (con teacher solo si la actividad ocupa su hora). */
#[Fillable(['school_id', 'coordinator_activity_id', 'teacher_id', 'date', 'schedule_slot_id', 'repeats', 'user_id'])]
class CoordinatorSession extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'repeats' => 'boolean',
        ];
    }

    /** @return BelongsTo<CoordinatorActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(CoordinatorActivity::class, 'coordinator_activity_id');
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

    /** Horas de la sesión (duración de su bloque de horario). */
    public function hours(): float
    {
        return $this->slot === null ? 1.0 : max(0.0, (strtotime($this->slot->ends_at) - strtotime($this->slot->starts_at)) / 3600);
    }

    public function auditLabel(): string
    {
        return ($this->activity?->name ?? '').' · '.$this->date?->toDateString();
    }
}
