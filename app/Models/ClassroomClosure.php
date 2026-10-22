<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Periodo en que un aula (audiovisual) no se puede usar: todo el día o de una hora a otra. */
#[Fillable(['school_id', 'classroom_id', 'starts_on', 'ends_on', 'from_slot_id', 'to_slot_id', 'reason', 'user_id'])]
class ClassroomClosure extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
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

    public function isAllDay(): bool
    {
        return $this->from_slot_id === null;
    }

    /** ¿Cierra el aula ese día a esa hora? (compara fechas de calendario, sin zona horaria). */
    public function covers(CarbonInterface $date, ScheduleSlot $slot): bool
    {
        $day = $date->toDateString();

        if ($day < $this->starts_on->toDateString() || $day > $this->ends_on->toDateString()) {
            return false;
        }

        return $this->isAllDay()
            || ($slot->starts_at >= $this->fromSlot->starts_at && $slot->starts_at <= $this->toSlot->starts_at);
    }
}
