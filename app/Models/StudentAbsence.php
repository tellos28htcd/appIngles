<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Solicitud de ausencia (pausa) del alumno. */
#[Fillable(['school_id', 'student_id', 'starts_on', 'ends_on', 'reason', 'user_id'])]
class StudentAbsence extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** Días naturales autorizados (incluye sábados y domingos; cuenta inicio y fin). */
    public function days(): int
    {
        return $this->ends_on === null ? 1 : (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
