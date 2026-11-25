<?php

namespace App\Actions\Kardex;

use App\Actions\Agenda\AgendaService;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\GradeType;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\KardexEntry;
use App\Models\School;
use App\Models\Student;
use App\Models\UngradedReason;
use App\Models\User;
use App\Support\ProgressPath;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kardex → Calificaciones. En cada reservación se califican, en orden y sin saltarse ninguna, la práctica que
 * indica el Kardex (la siguiente sin calificar de la ruta) y hasta 2 más si el alumno avanzó en la asesoría.
 * Las actividades de firma (1–3) y los clubs llevan F; las demás A, B o C. Se califica el mismo día o hasta
 * DAYS_BACK días después, con motivo y comentario; o se deja "Sin calificación" con motivo. Las presentaciones de la
 * ruta (A7 de las lecciones 15 y 17) se califican con A/B/C o quedan pendientes (PresentationService).
 */
final class KardexService
{
    public function __construct(private PresentationService $presentations) {}

    /** Prácticas que se muestran para calificar en cada reservación. */
    public const STEPS_SHOWN = 3;

    /** Días anteriores en que todavía se puede calificar (con motivo). */
    public const DAYS_BACK = 4;

    /** Valor de la calificación de una presentación que se deja pendiente. */
    public const PENDING = 'pending';

    // ── Ruta del alumno ───────────────────────────────────────────

    /** Posición en la ruta de lo último calificado (null: nada calificado). */
    public function lastGradedIndex(Student $student, ProgressPath $path): ?int
    {
        return KardexEntry::withoutGlobalScope('school')->where('student_id', $student->id)->where('kind', BookingKind::Session)
            ->whereNotNull('lesson_id')->get(['lesson_id', 'activity_number'])
            ->map(fn (KardexEntry $entry) => $path->nearestIndex($entry->lesson_id, $entry->activity_number, before: true))
            ->filter(fn ($index) => $index !== null)->max();
    }

    /**
     * Las prácticas por calificar en una reservación: la que indica el Kardex y las 2 siguientes. Sin nada calificado,
     * empieza en la práctica de la reservación.
     *
     * @return list<array{index: int, book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string, order: int, points: int, type: GradeType}>
     */
    public function pendingSteps(Booking $booking, ?ProgressPath $path = null): array
    {
        $student = $booking->student()->withoutGlobalScope('school')->firstOrFail();
        $path ??= new ProgressPath($student->school);
        $types = Activity::gradeTypesFor($booking->school_id);
        $withType = fn (int $index, array $step): array => ['index' => $index, ...$step, 'type' => $types[$step['activity_number']] ?? GradeType::forActivityNumber($step['activity_number'])];

        // Presentación de la ruta reservada como Presentación: solo esa actividad (la siguiente o la pendiente).
        if (in_array($booking->kind, PresentationService::KINDS, true) && $booking->lesson_id !== null) {
            $index = $path->indexOf($booking->lesson_id, $booking->activity_number);
            $entry = KardexEntry::withoutGlobalScope('school')->where('student_id', $student->id)->where('lesson_id', $booking->lesson_id)
                ->where('activity_number', $booking->activity_number)->first();

            return $index === null || ($entry !== null && ! $entry->is_pending) ? [] : [$withType($index, $path->at($index))];
        }

        $last = $this->lastGradedIndex($student, $path);
        $start = $last !== null ? $last + 1 : ($path->nearestIndex($booking->lesson_id, $booking->activity_number, before: false) ?? 0);
        // Con una presentación pendiente de otro libro, solo las actividades permitidas del libro nuevo.
        $blockedFrom = $this->presentations->blockedFrom($student, $path);

        $steps = [];
        for ($index = $start; $index < $start + self::STEPS_SHOWN; $index++) {
            $step = $path->at($index);
            if ($step === null || ($blockedFrom !== null && $index >= $blockedFrom)) {
                break;
            }
            $steps[] = $withType($index, $step);
        }

        return $steps;
    }

    /** Calificaciones activas de la escuela por tipo. @return EloquentCollection<int, Grade> */
    public function grades(int $schoolId, GradeType $type): EloquentCollection
    {
        return Grade::withoutGlobalScope('school')->where('school_id', $schoolId)->where('is_active', true)->where('type', $type)
            ->orderBy('sort_order')->orderBy('code')->get();
    }

    // ── Fechas ────────────────────────────────────────────────────

    /** Primer día que todavía se puede calificar (hoy menos DAYS_BACK). */
    public function earliestDate(CarbonInterface $today): CarbonInterface
    {
        return $today->copy()->subDays(self::DAYS_BACK)->startOfDay();
    }

    /** Se califica después del día de la práctica: pide motivo y comentario. */
    public function isLate(Booking $booking, CarbonInterface $today): bool
    {
        return $booking->date->toDateString() < $today->toDateString();
    }

    /** Para calificar, primero se registra la asistencia ("Asistió") en la Agenda. */
    private function ensureAttended(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::Attended) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.not_attended')]);
        }
    }

    private function ensureGradable(Booking $booking, CarbonInterface $today): void
    {
        $date = $booking->date->toDateString();

        // Mentoría especial y entrega de material no se califican.
        if (! $booking->status->isActive() || $booking->is_waitlist || ! $booking->kind->isGradable()) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.not_gradable')]);
        }
        if ($date > $today->toDateString()) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.future')]);
        }
        if ($date < $this->earliestDate($today)->toDateString()) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.too_old', ['days' => self::DAYS_BACK])]);
        }
    }

    /** Motivo activo de la escuela de la reservación que ese usuario puede elegir (teacher o personal). */
    private function reasonFor(Booking $booking, int|string|null $reasonId, ?User $actor): ?UngradedReason
    {
        if ($reasonId === null || $reasonId === '') {
            return null;
        }

        return UngradedReason::withoutGlobalScope('school')->where('school_id', $booking->school_id)->where('is_active', true)
            ->when($actor !== null, fn ($query) => $query->usableBy($actor))->find((int) $reasonId);
    }

    /** @return array{0: ?int, 1: ?string} */
    private function lateReason(Booking $booking, CarbonInterface $today, ?int $reasonId, ?string $comment, ?User $actor = null): array
    {
        if (! $this->isLate($booking, $today)) {
            return [null, null];
        }

        $comment = trim((string) $comment);
        $reason = $this->reasonFor($booking, $reasonId, $actor);

        $errors = array_filter([
            'lateReasonId' => $reason === null ? __('kardex.validation.late_reason') : null,
            'lateComment' => $comment === '' ? __('kardex.validation.late_comment') : (mb_strlen($comment) > 255 ? __('kardex.validation.comment_max') : null),
        ]);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$reason->id, $comment];
    }

    // ── Calificar ─────────────────────────────────────────────────

    /**
     * Guarda las prácticas calificadas de una reservación (en orden, sin huecos). $gradeIds sigue el orden de
     * pendingSteps(); las vacías al final no se guardan. Una presentación puede ir como PENDING (queda pendiente y se
     * sigue con las siguientes). $observationIds: la observación (motivo del Kardex, opcional) de cada práctica, en el
     * mismo orden.
     *
     * @param  list<int|string|null>  $gradeIds
     * @param  list<int|string|null>  $observationIds
     * @return int prácticas guardadas
     */
    public function gradeSession(Booking $booking, array $gradeIds, User $actor, CarbonInterface $today, ?int $lateReasonId = null, ?string $lateComment = null, array $observationIds = []): int
    {
        $this->ensureGradable($booking, $today);
        $this->ensureAttended($booking);
        $steps = $this->pendingSteps($booking);
        $gradeIds = array_map(fn ($id) => match (true) {
            $id === null || $id === '' => null,
            $id === self::PENDING => self::PENDING,
            default => (int) $id,
        }, array_slice(array_values($gradeIds), 0, count($steps)));

        $filled = count(array_filter($gradeIds));
        if ($filled === 0) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.none')]);
        }
        // Sin huecos: todas las calificadas van seguidas desde la primera.
        if (array_slice($gradeIds, 0, $filled) !== array_values(array_filter($gradeIds))) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.in_order')]);
        }

        $chosen = [];
        $student = Student::withoutGlobalScope('school')->findOrFail($booking->student_id);
        foreach (array_slice($steps, 0, $filled) as $position => $step) {
            if ($gradeIds[$position] === self::PENDING) {
                $this->ensureCanLeavePending($student, $step, $actor, $position);
            }
            $grade = $gradeIds[$position] === self::PENDING ? null : $this->grades($booking->school_id, $step['type'])->firstWhere('id', $gradeIds[$position]);
            if ($grade === null && $gradeIds[$position] !== self::PENDING) {
                throw ValidationException::withMessages(["grades.$position" => __('kardex.validation.grade_type', ['practice' => $step['label']])]);
            }
            $observationId = $observationIds[$position] ?? null;
            $observation = $this->reasonFor($booking, $observationId, $actor);
            if ($observationId !== null && $observationId !== '' && $observation === null) {
                throw ValidationException::withMessages(["observations.$position" => __('kardex.validation.observation', ['practice' => $step['label']])]);
            }
            $chosen[] = [$step, $grade, $observation];
        }

        [$reasonId, $comment] = $this->lateReason($booking, $today, $lateReasonId, $lateComment, $actor);

        DB::transaction(function () use ($booking, $chosen, $actor, $reasonId, $comment): void {
            foreach ($chosen as [$step, $grade, $observation]) {
                // La presentación pendiente se califica en su mismo renglón del Kardex.
                KardexEntry::withoutGlobalScope('school')->updateOrCreate(
                    ['student_id' => $booking->student_id, 'lesson_id' => $step['lesson_id'], 'activity_number' => $step['activity_number']],
                    [
                        'school_id' => $booking->school_id, 'booking_id' => $booking->id, 'kind' => BookingKind::Session, 'book_id' => $step['book_id'],
                        'sort_order' => $step['order'], 'points' => $step['points'], 'grade_id' => $grade?->id, 'is_pending' => $grade === null,
                        'teacher_id' => $booking->teacher_id, 'user_id' => $actor->id, 'session_date' => $booking->date->toDateString(),
                        'late_reason_id' => $reasonId, 'late_comment' => $comment, 'observation_reason_id' => $observation?->id,
                    ],
                );
            }

            $booking->update(['ungraded_reason_id' => null, 'ungraded_comment' => null]);
            $this->resequenceUpcoming($booking);
        });

        return count($chosen);
    }

    /**
     * Una presentación de la ruta queda pendiente solo si quien califica puede (teacher o Recepción) y no tiene otra.
     *
     * @param  array<string, mixed>  $step
     */
    private function ensureCanLeavePending(Student $student, array $step, User $actor, int $position): void
    {
        $current = $this->presentations->pending($student);
        $error = match (true) {
            ! PresentationService::isPresentation($step) => __('kardex.validation.pending_not_presentation', ['practice' => $step['label']]),
            ! $this->presentations->canLeavePending($actor) => __('kardex.validation.pending_role'),
            $current !== null && ($current->lesson_id !== $step['lesson_id'] || $current->activity_number !== $step['activity_number']) => __('kardex.validation.one_pending'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(["grades.$position" => $error]);
        }
    }

    /** Firma (F) del club o de una presentación sin actividad de la ruta (anterior a las presentaciones A7); se firma una sola vez. */
    public function gradeClub(Booking $booking, ?int $gradeId, User $actor, CarbonInterface $today, ?int $lateReasonId = null, ?string $lateComment = null, int|string|null $observationId = null): void
    {
        $this->ensureGradable($booking, $today);
        $this->ensureAttended($booking);

        if (KardexEntry::withoutGlobalScope('school')->where('booking_id', $booking->id)->exists()) {
            throw ValidationException::withMessages(['grades' => __('kardex.validation.club_done')]);
        }

        $grade = $gradeId ? $this->grades($booking->school_id, GradeType::Signature)->firstWhere('id', $gradeId) : null;
        if ($grade === null) {
            throw ValidationException::withMessages(['grades.0' => __('kardex.validation.club_grade')]);
        }

        $observation = $this->reasonFor($booking, $observationId, $actor);
        if ($observationId !== null && $observationId !== '' && $observation === null) {
            throw ValidationException::withMessages(['observations.0' => __('kardex.validation.observation', ['practice' => $booking->activityLabel()])]);
        }

        [$reasonId, $comment] = $this->lateReason($booking, $today, $lateReasonId, $lateComment, $actor);
        $club = $booking->clubSession()->withoutGlobalScope('school')->first();

        DB::transaction(function () use ($booking, $grade, $actor, $reasonId, $comment, $club, $observation): void {
            KardexEntry::withoutGlobalScope('school')->create([
                'school_id' => $booking->school_id, 'student_id' => $booking->student_id, 'booking_id' => $booking->id, 'kind' => $booking->kind,
                'club_session_id' => $booking->club_session_id, 'club_code' => $club?->code, 'grade_id' => $grade->id, 'teacher_id' => $booking->teacher_id ?? $club?->teacher_id,
                'user_id' => $actor->id, 'session_date' => $booking->date->toDateString(), 'late_reason_id' => $reasonId, 'late_comment' => $comment,
                'observation_reason_id' => $observation?->id,
            ]);
            $booking->update(['ungraded_reason_id' => null, 'ungraded_comment' => null]);
        });
    }

    /** "Sin calificación": motivo del catálogo y comentario; el Kardex no avanza. */
    public function markUngraded(Booking $booking, ?int $reasonId, ?string $comment, CarbonInterface $today, ?User $actor = null): void
    {
        $this->ensureGradable($booking, $today);

        $reason = $this->reasonFor($booking, $reasonId, $actor);
        $comment = trim((string) $comment);

        $errors = array_filter([
            'ungradedReasonId' => $reason === null ? __('kardex.validation.ungraded_reason') : null,
            'ungradedComment' => mb_strlen($comment) > 255 ? __('kardex.validation.comment_max') : null,
        ]);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $booking->update(['ungraded_reason_id' => $reason->id, 'ungraded_comment' => $comment === '' ? null : $comment]);
    }

    // ── Corregir (Coordinador académico / Administrador) ──────────

    public function changeGrade(KardexEntry $entry, int $gradeId): void
    {
        $type = ! $entry->kind->isPractice() ? GradeType::Signature
            : (Activity::gradeTypesFor($entry->school_id)[$entry->activity_number] ?? GradeType::forActivityNumber((int) $entry->activity_number));
        $grade = $this->grades($entry->school_id, $type)->firstWhere('id', $gradeId);

        if ($grade === null) {
            throw ValidationException::withMessages(['correction' => __('kardex.validation.grade_type', ['practice' => $entry->practiceLabel()])]);
        }

        // Si era una presentación pendiente, queda calificada.
        $entry->update(['grade_id' => $grade->id, 'is_pending' => false]);
    }

    /** Solo se quita la última práctica calificada del alumno (o un club), para no romper el orden. */
    public function remove(KardexEntry $entry): void
    {
        if ($entry->kind === BookingKind::Session && ! $this->isLastGraded($entry)) {
            throw ValidationException::withMessages(['correction' => __('kardex.validation.remove_last')]);
        }

        $entry->delete();
    }

    public function isLastGraded(KardexEntry $entry): bool
    {
        if ($entry->kind !== BookingKind::Session) {
            return true;
        }

        $student = Student::withoutGlobalScope('school')->findOrFail($entry->student_id);
        $path = new ProgressPath($student->school);

        return $path->nearestIndex($entry->lesson_id, $entry->activity_number, before: true) === $this->lastGradedIndex($student, $path);
    }

    // ── Agenda ────────────────────────────────────────────────────

    /**
     * Si el teacher calificó más prácticas de las reservadas, las siguientes reservaciones del alumno (aún sin
     * calificar) se recorren a las prácticas que siguen en el Kardex.
     */
    private function resequenceUpcoming(Booking $booking): void
    {
        $student = Student::withoutGlobalScope('school')->findOrFail($booking->student_id);
        $path = new ProgressPath($student->school);
        $next = ($this->lastGradedIndex($student, $path) ?? -1) + 1;
        $startsAt = (string) $booking->slot()->withoutGlobalScope('school')->value('starts_at');

        $upcoming = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->active()->where('kind', BookingKind::Session)
            ->whereKeyNot($booking->id)->whereNull('ungraded_reason_id')->whereDoesntHave('kardexEntries')
            ->where(fn ($query) => $query->whereDate('date', '>', $booking->date->toDateString())
                ->orWhere(fn ($query) => $query->whereDate('date', $booking->date->toDateString())
                    ->whereHas('slot', fn ($slot) => $slot->withoutGlobalScope('school')->where('starts_at', '>', $startsAt))))
            ->with(['slot' => fn ($slot) => $slot->withoutGlobalScope('school'), 'student' => fn ($query) => $query->withoutGlobalScope('school')])->get()
            ->sortBy(fn (Booking $item) => $item->date->toDateString().'|'.$item->slot?->starts_at)->values();

        foreach ($upcoming as $position => $item) {
            $step = $path->at($next + $position);
            if ($step === null) {
                break;
            }
            if ($item->lesson_id !== $step['lesson_id'] || $item->activity_number !== $step['activity_number']) {
                $item->update(['book_id' => $step['book_id'], 'lesson_id' => $step['lesson_id'], 'activity_number' => $step['activity_number']]);
            }
        }
    }

    // ── Kardex del alumno ─────────────────────────────────────────

    /**
     * Resumen del Kardex: práctica que sigue, puntos acumulados (los de la última calificada) y lo calificado.
     *
     * @return array{next: ?array<string, mixed>, points: int, graded: int, pending: ?KardexEntry, total: int, entries: Collection<int, KardexEntry>}
     */
    public function summary(Student $student): array
    {
        $school = School::findOrFail($student->school_id);
        $path = new ProgressPath($school);
        $last = $this->lastGradedIndex($student, $path);
        $entries = KardexEntry::withoutGlobalScope('school')->where('student_id', $student->id)
            ->with(['grade' => fn ($q) => $q->withoutGlobalScope('school'), 'teacher' => fn ($q) => $q->withoutGlobalScope('school'),
                'book' => fn ($q) => $q->withoutGlobalScope('school'), 'lesson' => fn ($q) => $q->withoutGlobalScope('school'),
                'lateReason' => fn ($q) => $q->withoutGlobalScope('school'), 'observationReason' => fn ($q) => $q->withoutGlobalScope('school')])
            ->orderByDesc('session_date')->orderByDesc('sort_order')->orderByDesc('id')->get();

        return [
            'next' => $path->at($last !== null ? $last + 1 : app(AgendaService::class)->startIndex($student, $path)),
            'points' => $last !== null ? (int) ($path->at($last)['points'] ?? 0) : 0,
            'graded' => $entries->where('kind', BookingKind::Session)->where('is_pending', false)->count(),
            'pending' => $entries->firstWhere('is_pending', true),
            'total' => $path->steps()->count(),
            'entries' => $entries,
        ];
    }
}
