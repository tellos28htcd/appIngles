<?php

namespace App\Models;

use App\Enums\StudentTermMovementType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del plazo del curso (reinscripción, extensión de tiempo, ajuste
 * de días o mensualidad abierta). Sus días se suman al fin del curso.
 */
#[Fillable(['school_id', 'student_id', 'student_enrollment_id', 'type', 'occurred_on', 'days', 'closed_on', 'reason', 'user_id'])]
class StudentTermMovement extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'type' => StudentTermMovementType::class,
            'occurred_on' => 'date',
            'closed_on' => 'date',
            'days' => 'integer',
        ];
    }

    /** Mensualidad abierta que aún no se cierra (sus días son estimados). */
    public function isOpen(): bool
    {
        return $this->type === StudentTermMovementType::OpenMonthly && $this->closed_on === null;
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        return $this->type->label().' · '.$this->occurred_on?->toDateString();
    }
}
