<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bloque de horario numerado (p. ej. 1 · 07:00–08:00 · Matutino). */
#[Fillable(['school_id', 'base_id', 'shift_id', 'number', 'starts_at', 'ends_at', 'is_active'])]
class ScheduleSlot extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** Si ya se usa en horarios de teachers, en el panel o en incidencias, solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return ! TeacherScheduleBlock::withoutGlobalScope('school')->where('schedule_slot_id', $this->id)->exists()
            && ! TeacherAvailability::withoutGlobalScope('school')->where('schedule_slot_id', $this->id)->exists()
            && ! TeacherIncident::withoutGlobalScope('school')->where('from_slot_id', $this->id)->orWhere('to_slot_id', $this->id)->exists();
    }

    public function range(): string
    {
        return substr($this->starts_at, 0, 5).'–'.substr($this->ends_at, 0, 5);
    }
}
