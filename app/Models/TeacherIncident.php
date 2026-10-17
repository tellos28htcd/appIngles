<?php

namespace App\Models;

use App\Enums\TeacherIncidentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incidencia del horario: permiso, vacaciones, incapacidad, falta, evento
 * imprevisto o "Cubre horas" (otro teacher toma sus horas). Sin horas = día completo.
 */
#[Fillable(['school_id', 'teacher_id', 'type', 'starts_on', 'ends_on', 'from_slot_id', 'to_slot_id', 'covered_teacher_id', 'reason', 'user_id'])]
class TeacherIncident extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'type' => TeacherIncidentType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function coveredTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'covered_teacher_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ScheduleSlot, $this> */
    public function fromSlot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'from_slot_id');
    }

    /** @return BelongsTo<ScheduleSlot, $this> */
    public function toSlot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'to_slot_id');
    }

    /** ¿La incidencia cubre esa fecha y ese bloque de horario? */
    public function covers(CarbonInterface $date, ScheduleSlot $slot): bool
    {
        if ($date->lt($this->starts_on) || $date->gt($this->ends_on)) {
            return false;
        }

        if ($this->from_slot_id === null) {
            return true;
        }

        return $slot->starts_at >= $this->fromSlot->starts_at && $slot->starts_at <= $this->toSlot->starts_at;
    }

    public function auditLabel(): string
    {
        return $this->type->label().' · '.$this->starts_on->toDateString();
    }
}
