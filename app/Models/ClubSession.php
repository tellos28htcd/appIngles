<?php

namespace App\Models;

use App\Enums\ClubModality;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Club programado: abreviatura del club (C12, B23…) con su teacher, fecha, hora,
 * aula audiovisual y modalidad. Esa hora del teacher deja de estar disponible
 * para clases normales.
 */
#[Fillable(['school_id', 'club_id', 'code', 'teacher_id', 'classroom_id', 'date', 'schedule_slot_id', 'modality', 'repeats', 'user_id'])]
class ClubSession extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'modality' => ClubModality::class,
            'repeats' => 'boolean',
        ];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
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

    /** Color de la abreviatura (paleta del panel, 1…9). */
    public function color(): int
    {
        return (int) ($this->club?->codeInfo($this->code)['color'] ?? 1);
    }

    public function auditLabel(): string
    {
        return $this->code.' · '.$this->date?->toDateString();
    }
}
