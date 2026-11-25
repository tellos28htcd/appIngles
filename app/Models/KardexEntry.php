<?php

namespace App\Models;

use App\Enums\BookingKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón del Kardex del alumno: una práctica de la ruta (libro + lección + actividad, con su orden) o un club,
 * con su calificación, la reservación en que se calificó, el teacher y quién la capturó. Si se capturó un día
 * después de la práctica, lleva el motivo y el comentario. Puede llevar una observación (motivo del Kardex) para el
 * seguimiento del alumno. Una presentación (A7 de las lecciones 15 y 17) puede quedar pendiente: sin calificación
 * (is_pending) para que el alumno siga con las actividades siguientes; al presentarla se califica ese mismo renglón.
 */
#[Fillable([
    'school_id', 'student_id', 'booking_id', 'kind', 'book_id', 'lesson_id', 'activity_number', 'sort_order', 'points',
    'club_session_id', 'club_code', 'grade_id', 'is_pending', 'teacher_id', 'user_id', 'session_date', 'late_reason_id', 'late_comment',
    'observation_reason_id',
])]
class KardexEntry extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'session_date' => 'date',
            'activity_number' => 'integer',
            'sort_order' => 'integer',
            'points' => 'integer',
            'is_pending' => 'boolean',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<UngradedReason, $this> */
    public function lateReason(): BelongsTo
    {
        return $this->belongsTo(UngradedReason::class, 'late_reason_id');
    }

    /** @return BelongsTo<UngradedReason, $this> */
    public function observationReason(): BelongsTo
    {
        return $this->belongsTo(UngradedReason::class, 'observation_reason_id');
    }

    /** Para la bitácora (sin cargar relaciones). */
    public function auditLabel(): string
    {
        return match ($this->kind) {
            BookingKind::Session => 'Orden '.$this->sort_order,
            BookingKind::Club => (string) $this->club_code,
            default => $this->practiceLabel(),
        };
    }

    /** Práctica como en el catálogo ("L1-L3-A2-1P") o la abreviatura del club. */
    public function practiceLabel(): string
    {
        if ($this->kind === BookingKind::Club) {
            return (string) $this->club_code;
        }

        // Presentación 1 a 1 o en club (sin actividad de la ruta).
        if ($this->kind === BookingKind::ClubPresentation) {
            return __('agenda.option_club_presentation', ['code' => (string) $this->club_code]);
        }
        if ($this->kind !== BookingKind::Session) {
            return $this->kind->label();
        }

        return $this->book && $this->lesson && $this->activity_number
            ? Practice::labelFor((int) $this->book->level, $this->lesson, $this->activity_number,
                Practice::namesFor($this->school_id)[$this->lesson_id.'|'.$this->activity_number] ?? null, Activity::codesFor($this->school_id))
            : '—';
    }
}
