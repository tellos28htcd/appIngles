<?php

namespace App\Models;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CancellationReason;
use App\Enums\ClubModality;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reservación de una hora en la agenda: asesoría (con la actividad que le toca, LLA)
 * o club; presencial (aula asignada por el sistema) o en línea (aula virtual).
 */
#[Fillable([
    'school_id', 'student_id', 'date', 'schedule_slot_id', 'kind', 'modality', 'teacher_availability_id', 'club_session_id',
    'teacher_id', 'classroom_id', 'virtual_room', 'is_waitlist', 'status', 'cancellation_reason', 'cancelled_at',
    'book_id', 'lesson_id', 'activity_number', 'booked_by_reception', 'user_id',
])]
class Booking extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => BookingKind::class,
            'modality' => ClubModality::class,
            'status' => BookingStatus::class,
            'cancellation_reason' => CancellationReason::class,
            'cancelled_at' => 'datetime',
            'is_waitlist' => 'boolean',
            'booked_by_reception' => 'boolean',
            'virtual_room' => 'integer',
            'activity_number' => 'integer',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', BookingStatus::Cancelled->value);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<ScheduleSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }

    /** @return BelongsTo<TeacherAvailability, $this> */
    public function cell(): BelongsTo
    {
        return $this->belongsTo(TeacherAvailability::class, 'teacher_availability_id');
    }

    /** @return BelongsTo<ClubSession, $this> */
    public function clubSession(): BelongsTo
    {
        return $this->belongsTo(ClubSession::class);
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

    /** @return HasMany<BookingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->orderBy('id');
    }

    /** Actividad que le toca, p. ej. "L1-L3-A4" (o la abreviatura del club). */
    public function activityLabel(): string
    {
        if ($this->kind === BookingKind::Club) {
            return (string) $this->clubSession?->code;
        }

        return $this->book && $this->lesson && $this->activity_number
            ? "L{$this->book->level}-L{$this->lesson->number}-A{$this->activity_number}"
            : '—';
    }

    /** Lugar asignado: aula, aula virtual o lista de espera. */
    public function placeLabel(): string
    {
        return match (true) {
            $this->is_waitlist => __('agenda.waitlist'),
            $this->modality === ClubModality::Online && $this->classroom_id === null => __('agenda.virtual_room', ['number' => $this->virtual_room ?? 1]),
            default => __('agenda.room', ['name' => $this->classroom?->name ?? '—']),
        };
    }

    public function auditLabel(): string
    {
        return ($this->student?->fullName() ?? '').' · '.$this->date?->toDateString();
    }
}
