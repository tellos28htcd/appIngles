<?php

namespace App\Actions\Kardex;

use App\Enums\BookingKind;
use App\Models\Booking;
use App\Models\KardexEntry;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\ProgressPath;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Presentaciones de la ruta: las actividades marcadas como presentación en el catálogo de Prácticas (A7 de las
 * lecciones 15 y 17 de cada libro). Se agendan como Presentación (1 a 1 presencial o en línea, o en el club de esa
 * hora) y se califican con A/B/C. El teacher o Recepción pueden dejarla **pendiente** (una a la vez): el Kardex
 * sigue con las actividades siguientes y la presentación queda a la vista hasta que la haga. Si cambia de libro
 * con una pendiente, puede hacer BOOK_CHANGE_GRACE actividades del libro nuevo y después se bloquea.
 */
final class PresentationService
{
    /** Actividades del libro siguiente que puede hacer con una presentación pendiente del libro anterior. */
    public const BOOK_CHANGE_GRACE = 2;

    /** Tipos de reservación de una presentación. */
    public const KINDS = [BookingKind::Presentation, BookingKind::ClubPresentation];

    /** @param  array<string, mixed>|null  $step */
    public static function isPresentation(?array $step): bool
    {
        return (bool) ($step['is_presentation'] ?? false);
    }

    /** Dejan una presentación pendiente: el teacher y Recepción. */
    public function canLeavePending(User $actor): bool
    {
        return in_array($actor->loadMissing('role')->role?->slug, [Role::TEACHER, Role::RECEPTION], true);
    }

    public function pending(Student $student): ?KardexEntry
    {
        return KardexEntry::withoutGlobalScope('school')->where('student_id', $student->id)->where('is_pending', true)->first();
    }

    /**
     * Paso de la ruta de su presentación pendiente (con su posición).
     *
     * @return array<string, mixed>|null
     */
    public function pendingStep(Student $student, ProgressPath $path): ?array
    {
        $entry = $this->pending($student);
        $index = $entry ? $path->indexOf($entry->lesson_id, $entry->activity_number) : null;

        return $index === null ? null : ['index' => $index, ...$path->at($index)];
    }

    /**
     * Presentación que le toca agendar: la pendiente o, sin pendiente, su siguiente actividad si es presentación.
     * Si ya tiene reservada esa presentación, no le toca otra.
     *
     * @param  array<string, mixed>|null  $next  siguiente actividad de la ruta (AgendaService::nextActivity)
     * @return array{step: array<string, mixed>, pending: bool}|null
     */
    public function turn(Student $student, ProgressPath $path, ?array $next): ?array
    {
        $pending = $this->pendingStep($student, $path);
        $turn = match (true) {
            $pending !== null => ['step' => $pending, 'pending' => true],
            self::isPresentation($next) => ['step' => $next, 'pending' => false],
            default => null,
        };

        if ($turn === null || $this->isBooked($student, $turn['step'])) {
            return null;
        }

        return $turn;
    }

    /** Ya tiene esa presentación reservada (activa y sin "Sin calificación"). @param  array<string, mixed>  $step */
    public function isBooked(Student $student, array $step, ?int $exceptBookingId = null): bool
    {
        return Booking::withoutGlobalScope('school')->where('student_id', $student->id)->active()->whereNull('ungraded_reason_id')
            ->whereIn('kind', self::KINDS)->where('lesson_id', $step['lesson_id'])->where('activity_number', $step['activity_number'])
            ->when($exceptBookingId !== null, fn ($query) => $query->whereKeyNot($exceptBookingId))->exists();
    }

    /**
     * Posición de la ruta desde la que ya no puede avanzar: con una presentación pendiente de un libro, las
     * BOOK_CHANGE_GRACE primeras actividades del libro siguiente sí; después, hasta que la presente.
     */
    public function blockedFrom(Student $student, ProgressPath $path): ?int
    {
        $pending = $this->pendingStep($student, $path);
        $nextBook = $pending ? $path->firstAboveLevel((int) $pending['level']) : null;

        return $nextBook === null ? null : $nextBook + self::BOOK_CHANGE_GRACE;
    }

    /**
     * Por qué ese paso no se agenda como práctica: le toca su presentación (se agenda como Presentación) o tiene
     * una pendiente y ya hizo las actividades permitidas del libro nuevo.
     *
     * @param  array<string, mixed>|null  $step
     * @return array{0: string, 1: array<string, string>}|null [clave de agenda.blocked / agenda.validation, reemplazos]
     */
    public function practiceBlock(Student $student, ProgressPath $path, ?array $step): ?array
    {
        if ($step === null) {
            return null;
        }
        if (self::isPresentation($step)) {
            return ['presentation_turn', ['practice' => $step['label']]];
        }

        $blockedFrom = $this->blockedFrom($student, $path);
        $index = $path->indexOf($step['lesson_id'], $step['activity_number']);
        if ($blockedFrom !== null && $index !== null && $index >= $blockedFrom) {
            return ['presentation_blocked', ['practice' => (string) ($this->pendingStep($student, $path)['label'] ?? '')]];
        }

        return null;
    }

    /**
     * Deja pendiente una presentación (una a la vez): queda en el Kardex sin calificación y la ruta sigue con la
     * actividad siguiente.
     *
     * @param  array<string, mixed>  $step
     */
    public function leavePending(Student $student, array $step, User $actor, CarbonInterface $date, ?Booking $booking = null): KardexEntry
    {
        if (! $this->canLeavePending($actor)) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.pending_role')]);
        }
        if (! self::isPresentation($step)) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.pending_not_presentation', ['practice' => $step['label']])]);
        }
        $current = $this->pending($student);
        if ($current !== null && ($current->lesson_id !== $step['lesson_id'] || $current->activity_number !== $step['activity_number'])) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.one_pending')]);
        }

        return KardexEntry::withoutGlobalScope('school')->updateOrCreate(
            ['student_id' => $student->id, 'lesson_id' => $step['lesson_id'], 'activity_number' => $step['activity_number']],
            [
                'school_id' => $student->school_id, 'booking_id' => $booking?->id, 'kind' => BookingKind::Session, 'book_id' => $step['book_id'],
                'sort_order' => $step['order'], 'points' => $step['points'], 'grade_id' => null, 'is_pending' => true,
                'teacher_id' => $booking?->teacher_id, 'user_id' => $actor->id, 'session_date' => ($booking?->date ?? $date)->toDateString(),
            ],
        );
    }
}
