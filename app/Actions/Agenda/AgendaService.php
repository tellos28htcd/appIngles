<?php

namespace App\Actions\Agenda;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CancellationReason;
use App\Enums\ClubModality;
use App\Enums\SelfBooking;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\ClubSession;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Support\ProgressPath;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agenda: lugares disponibles, reservación, cancelación, asistencia y lista de espera.
 *
 * - Asesoría presencial: las horas del panel (teacher + aula; sin clubs ni revisiones); cupo por
 *   aula = "cupo por sesión" de Mi Instituto (o el del aula si es menor). El aula se asigna al azar
 *   entre las que tienen lugar; llenas, el alumno entra a la lista de espera (si Mi Instituto lo permite).
 * - Asesoría en línea: "aulas virtuales por horario" × "cupo por aula virtual", a las horas con teachers.
 * - Club: los clubs programados; cupo "por club" (presencial) o "por aula virtual" (en línea);
 *   nivel de la abreviatura, lección mínima para clubs y máximo de clubs por día.
 * - Por día: máximo de asesorías (Mi Instituto) y de horas según la asistencia de los últimos
 *   7 días (≥ 0.8 o sin historial: 2 horas; menos: 1).
 * - Cada asesoría lleva la actividad que le toca (LLA) según la ruta de avance.
 */
final class AgendaService
{
    public const FULL_ATTENDANCE_HOURS = 2;

    public const LOW_ATTENDANCE_HOURS = 1;

    public const ATTENDANCE_THRESHOLD = 0.8;

    public function __construct(private AvailabilityPlanner $planner) {}

    // ── Avance (LLA) ──────────────────────────────────────────────

    /**
     * Siguiente actividad del alumno: la que sigue a la última asesoría reservada (no cancelada) o,
     * sin reservaciones, el punto de inicio de su ficha de inscripción.
     *
     * @return array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string}|null
     */
    public function nextActivity(Student $student, ?ProgressPath $path = null, int $offset = 0): ?array
    {
        $path ??= new ProgressPath($student->school);
        $last = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->active()
            ->where('kind', BookingKind::Session)->whereNotNull('lesson_id')->get(['lesson_id', 'activity_number'])
            ->map(fn (Booking $booking) => $path->indexOf($booking->lesson_id, $booking->activity_number))->filter(fn ($index) => $index !== null)->max();

        if ($last !== null) {
            return $path->at($last + 1 + $offset);
        }

        $enrollment = $student->enrollments()->withoutGlobalScope('school')->with('startActivity')->latest('registered_on')->latest('id')->first();
        $start = $path->indexOf($enrollment?->start_lesson_id, $enrollment?->startActivity?->number)
            ?? $path->firstOfLesson($enrollment?->start_lesson_id) ?? 0;

        return $path->at($start + $offset);
    }

    // ── Límites del alumno ────────────────────────────────────────

    /** Asistencia de los últimos 7 días (asistió ÷ asistió + faltó); null sin historial. */
    public function attendanceIndex(Student $student, CarbonInterface $today): ?float
    {
        $bookings = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->where('is_waitlist', false)
            ->whereBetween('date', [$today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString()])->get();

        $attended = $bookings->filter(fn (Booking $booking) => $booking->status === BookingStatus::Attended || $booking->cancellation_reason?->attendance() === true)->count();
        $absent = $bookings->filter(fn (Booking $booking) => $booking->cancellation_reason?->attendance() === false)->count();

        return $attended + $absent === 0 ? null : $attended / ($attended + $absent);
    }

    /** Horas que puede reservar por día según su asistencia (nuevo o ≥ 0.8: 2; menos: 1). */
    public function dailyLimit(Student $student, CarbonInterface $today): int
    {
        $index = $this->attendanceIndex($student, $today);

        return $index === null || $index >= self::ATTENDANCE_THRESHOLD ? self::FULL_ATTENDANCE_HOURS : self::LOW_ATTENDANCE_HOURS;
    }

    // ── Lugares ───────────────────────────────────────────────────

    /** Horas del panel con aula para asesoría (sin clubs ni revisiones) a esa hora. @return Collection<int, TeacherAvailability> */
    private function sessionCells(School $school, CarbonInterface $date, ScheduleSlot $slot): Collection
    {
        return TeacherAvailability::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->where('schedule_slot_id', $slot->id)->where('is_absent', false)->whereNotNull('classroom_id')
            ->whereNull('club_session_id')->whereNull('coordinator_session_id')
            ->with(['teacher:id,is_waitlist,short_name,first_name', 'classroom:id,name,capacity'])->get();
    }

    private function cellCapacity(School $school, TeacherAvailability $cell): int
    {
        return max(1, min($school->session_capacity ?: PHP_INT_MAX, $cell->classroom?->capacity ?: PHP_INT_MAX));
    }

    /** @return Collection<int, int> id de la celda => alumnos (sin lista de espera) */
    private function cellOccupancy(Collection $cells): Collection
    {
        return Booking::withoutGlobalScope('school')->active()->where('is_waitlist', false)->whereIn('teacher_availability_id', $cells->modelKeys())
            ->selectRaw('teacher_availability_id, count(*) as total')->groupBy('teacher_availability_id')->pluck('total', 'teacher_availability_id');
    }

    /** Aulas (presenciales) con lugar a esa hora. @return Collection<int, TeacherAvailability> */
    public function freeSessionCells(School $school, CarbonInterface $date, ScheduleSlot $slot): Collection
    {
        $cells = $this->sessionCells($school, $date, $slot)->reject(fn (TeacherAvailability $cell) => $cell->teacher?->is_waitlist);
        $taken = $this->cellOccupancy($cells);

        return $cells->filter(fn (TeacherAvailability $cell) => ($taken[$cell->id] ?? 0) < $this->cellCapacity($school, $cell))->values();
    }

    /** Aulas virtuales (1…N) con lugar a esa hora para asesoría en línea. @return list<int> */
    public function freeVirtualRooms(School $school, CarbonInterface $date, ScheduleSlot $slot): array
    {
        return array_keys($this->virtualSeats($school, $date, $slot));
    }

    /** Lugares libres por aula virtual a esa hora (solo las que tienen lugar). @return array<int, int> */
    private function virtualSeats(School $school, CarbonInterface $date, ScheduleSlot $slot): array
    {
        $rooms = (int) $school->virtual_rooms_per_slot;
        $hasTeachers = $this->sessionCells($school, $date, $slot)->contains(fn (TeacherAvailability $cell) => ! $cell->teacher?->is_waitlist);

        if ($rooms < 1 || ! $hasTeachers) {
            return [];
        }

        $capacity = max(1, (int) ($school->virtual_room_capacity ?: $school->session_capacity ?: 1));
        $taken = Booking::withoutGlobalScope('school')->active()->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->where('schedule_slot_id', $slot->id)->where('kind', BookingKind::Session)->where('modality', ClubModality::Online)
            ->selectRaw('virtual_room, count(*) as total')->groupBy('virtual_room')->pluck('total', 'virtual_room');

        return collect(range(1, $rooms))->mapWithKeys(fn (int $room) => [$room => $capacity - (int) ($taken[$room] ?? 0)])
            ->filter(fn (int $free) => $free > 0)->all();
    }

    private function clubCapacity(School $school, ClubSession $club): int
    {
        return max(1, (int) ($club->modality === ClubModality::Online
            ? ($school->virtual_room_capacity ?: $school->session_capacity ?: 1)
            : ($school->club_capacity ?: $club->classroom?->capacity ?: $school->session_capacity ?: 1)));
    }

    /** Lugares libres en un club. */
    public function clubFreeSeats(School $school, ClubSession $club): int
    {
        $taken = Booking::withoutGlobalScope('school')->active()->where('club_session_id', $club->id)->where('is_waitlist', false)->count();

        return max(0, $this->clubCapacity($school, $club) - $taken);
    }

    /** ¿El alumno puede ir a ese club? (nivel de la abreviatura y lección mínima para clubs). */
    public function clubAllows(School $school, ClubSession $club, ?array $step, ProgressPath $path): bool
    {
        if ($step === null) {
            return false;
        }

        $levels = $club->club?->codeInfo($club->code)['levels'] ?? [];
        if ($levels !== [] && ! in_array($step['level'], array_map('intval', $levels), true)) {
            return false;
        }

        if ($school->club_min_lesson_number) {
            // Solo aplica al primer libro: antes de esa lección el alumno aún no entra a clubs.
            $first = $path->steps()->first();
            $minimum = $path->steps()->search(fn (array $item) => $item['book_id'] === $first['book_id'] && $item['lesson_number'] === (int) $school->club_min_lesson_number);
            $current = $path->indexOf($step['lesson_id'], $step['activity_number']);

            if ($step['book_id'] === $first['book_id'] && $minimum !== false && $current !== null && $current < $minimum) {
                return false;
            }
        }

        return true;
    }

    /**
     * Opciones de un día para el alumno: por hora, asesoría presencial/en línea y clubs con lugar.
     *
     * @return list<array{slot: ScheduleSlot, options: list<array{kind: string, modality: string, club_session_id: ?int, label: string, free: int, waitlist: bool}>}>
     */
    public function options(School $school, Student $student, CarbonImmutable $date): array
    {
        if (! $this->planner->isGenerated($school, $date->startOfWeek(CarbonInterface::MONDAY))) {
            return [];
        }

        $path = new ProgressPath($school);
        $step = $this->nextActivity($student, $path);
        $clubs = ClubSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())->with(['club', 'classroom'])->get();
        $result = [];

        foreach ($this->planner->slots($school) as $slot) {
            if (! $this->isFuture($school, $date, $slot)) {
                continue;
            }

            $options = [];
            $cells = $this->sessionCells($school, $date, $slot);
            $free = $this->freeSessionCells($school, $date, $slot);
            $taken = $this->cellOccupancy($free);
            $freeSeats = $free->sum(fn (TeacherAvailability $cell) => $this->cellCapacity($school, $cell) - ($taken[$cell->id] ?? 0));

            if ($freeSeats > 0) {
                $options[] = ['kind' => BookingKind::Session->value, 'modality' => ClubModality::InPerson->value, 'club_session_id' => null,
                    'label' => __('agenda.option_session'), 'free' => $freeSeats, 'waitlist' => false];
            } elseif ($school->books_without_classroom && $cells->isNotEmpty()) {
                $options[] = ['kind' => BookingKind::Session->value, 'modality' => ClubModality::InPerson->value, 'club_session_id' => null,
                    'label' => __('agenda.option_session'), 'free' => 0, 'waitlist' => true];
            }

            $virtualSeats = array_sum($this->virtualSeats($school, $date, $slot));
            if ($virtualSeats > 0) {
                $options[] = ['kind' => BookingKind::Session->value, 'modality' => ClubModality::Online->value, 'club_session_id' => null,
                    'label' => __('agenda.option_session'), 'free' => $virtualSeats, 'waitlist' => false];
            }

            foreach ($clubs->where('schedule_slot_id', $slot->id) as $club) {
                $seats = $this->clubFreeSeats($school, $club);

                if ($seats > 0 && $this->clubAllows($school, $club, $step, $path)) {
                    $options[] = ['kind' => BookingKind::Club->value, 'modality' => $club->modality->value, 'club_session_id' => $club->id,
                        'label' => __('agenda.option_club', ['code' => $club->code, 'name' => $club->club?->name]), 'free' => $seats, 'waitlist' => false];
                }
            }

            if ($options !== []) {
                $result[] = ['slot' => $slot, 'options' => $options];
            }
        }

        return $result;
    }

    /** ¿La hora todavía no empieza? (en la zona horaria de la escuela). */
    private function isFuture(School $school, CarbonInterface $date, ScheduleSlot $slot): bool
    {
        $timezone = $school->timezone ?: config('appingles.timezone');

        return CarbonImmutable::parse($date->toDateString().' '.$slot->starts_at, $timezone)->isAfter(CarbonImmutable::now($timezone));
    }

    // ── Reservar ──────────────────────────────────────────────────

    /** Reserva una hora para el alumno (lo hace él mismo o recepción). */
    public function book(School $school, Student $student, CarbonImmutable $date, ScheduleSlot $slot, BookingKind $kind, ClubModality $modality,
        ?int $clubSessionId, ?User $user, bool $byReception): Booking
    {
        $fail = fn (string $key, array $replace = []) => throw ValidationException::withMessages(['booking' => __("agenda.validation.$key", $replace)]);
        $timezone = $school->timezone ?: config('appingles.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if (! $this->planner->isGenerated($school, $date->startOfWeek(CarbonInterface::MONDAY))) {
            $fail('week_not_ready');
        }

        if (! $this->isFuture($school, $date, $slot)) {
            $fail('past');
        }

        if (! $byReception) {
            match ($school->self_booking) {
                SelfBooking::Disabled => $fail('self_disabled'),
                SelfBooking::DayBefore => $date->lte($today) ? $fail('day_before') : null,
                default => null,
            };
        }

        return DB::transaction(function () use ($school, $student, $date, $slot, $kind, $modality, $clubSessionId, $user, $byReception, $fail, $today): Booking {
            // Bloquea las reservaciones del alumno ese día para no duplicar ni pasar los límites con dos clics.
            $day = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->whereDate('date', $date->toDateString())->active()->lockForUpdate()->get();

            if ($day->contains('schedule_slot_id', $slot->id)) {
                $fail('duplicate');
            }

            $limit = $this->dailyLimit($student, $today);
            if ($day->count() >= $limit) {
                $fail('daily_limit', ['hours' => $limit]);
            }

            if ($kind === BookingKind::Session && $school->max_sessions && $day->where('kind', BookingKind::Session)->count() >= $school->max_sessions) {
                $fail('max_sessions', ['max' => $school->max_sessions]);
            }

            if ($kind === BookingKind::Club && $school->max_clubs && $day->where('kind', BookingKind::Club)->count() >= $school->max_clubs) {
                $fail('max_clubs', ['max' => $school->max_clubs]);
            }

            $path = new ProgressPath($school);
            $booking = new Booking([
                'school_id' => $school->id, 'student_id' => $student->id, 'date' => $date->toDateString(), 'schedule_slot_id' => $slot->id,
                'kind' => $kind, 'modality' => $modality, 'status' => BookingStatus::Booked, 'booked_by_reception' => $byReception, 'user_id' => $user?->id,
            ]);

            if ($kind === BookingKind::Club) {
                $club = ClubSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())
                    ->where('schedule_slot_id', $slot->id)->with('club')->lockForUpdate()->find($clubSessionId);

                if ($club === null || ! $this->clubAllows($school, $club, $this->nextActivity($student, $path), $path)) {
                    $fail('club_not_allowed');
                }
                if ($this->clubFreeSeats($school, $club) < 1) {
                    $fail('full');
                }

                $booking->fill(['club_session_id' => $club->id, 'modality' => $club->modality, 'teacher_id' => $club->teacher_id, 'classroom_id' => $club->classroom_id]);
            } else {
                $step = $this->nextActivity($student, $path) ?? $fail('no_activity');
                $booking->fill(['book_id' => $step['book_id'], 'lesson_id' => $step['lesson_id'], 'activity_number' => $step['activity_number']]);

                if ($modality === ClubModality::Online) {
                    $rooms = $this->freeVirtualRooms($school, $date, $slot);
                    $rooms === [] && $fail('full');
                    $booking->virtual_room = $rooms[array_rand($rooms)];
                } else {
                    $this->placeInRoom($school, $booking, $date, $slot) || $fail('full');
                }
            }

            $booking->save();
            $this->log($booking, $booking->is_waitlist ? 'waitlisted' : 'booked', $booking->placeLabel(), $user);

            return $booking;
        });
    }

    /** Aula al azar entre las que tienen lugar; si están llenas, a la lista de espera (si se permite). */
    private function placeInRoom(School $school, Booking $booking, CarbonInterface $date, ScheduleSlot $slot): bool
    {
        $free = $this->freeSessionCells($school, $date, $slot);

        if ($free->isNotEmpty()) {
            $cell = $free->random();
            $booking->fill(['teacher_availability_id' => $cell->id, 'teacher_id' => $cell->teacher_id, 'classroom_id' => $cell->classroom_id, 'is_waitlist' => false]);

            return true;
        }

        if (! $school->books_without_classroom || $this->sessionCells($school, $date, $slot)->isEmpty()) {
            return false;
        }

        // Aula de espera: la columna "Lista de espera" del panel (si la hay a esa hora).
        $waitlist = $this->sessionCells($school, $date, $slot)->first(fn (TeacherAvailability $cell) => $cell->teacher?->is_waitlist);
        $booking->fill(['teacher_availability_id' => $waitlist?->id, 'teacher_id' => $waitlist?->teacher_id, 'classroom_id' => $waitlist?->classroom_id, 'is_waitlist' => true]);

        return true;
    }

    // ── Cancelar, asistencia y lista de espera ────────────────────

    public function cancel(Booking $booking, CancellationReason $reason, ?User $user): void
    {
        abort_if($booking->status === BookingStatus::Cancelled, 422);

        $booking->update(['status' => BookingStatus::Cancelled, 'cancellation_reason' => $reason, 'cancelled_at' => now()]);
        $this->log($booking, 'cancelled', $reason->label(), $user);
    }

    public function markAttended(Booking $booking, ?User $user): void
    {
        abort_if($booking->status === BookingStatus::Cancelled, 422);

        $booking->update(['status' => BookingStatus::Attended]);
        $this->log($booking, 'attended', null, $user);
    }

    /**
     * Alumnos en lista de espera de ese día a horas donde ya se liberó un lugar (se proponen para agendarlos).
     *
     * @return Collection<int, Booking>
     */
    public function waitlistProposals(School $school, CarbonInterface $date): Collection
    {
        return Booking::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->active()->where('is_waitlist', true)->with(['student', 'slot'])->orderBy('created_at')->get()
            ->filter(fn (Booking $booking) => $booking->slot && $this->freeSessionCells($school, $date, $booking->slot)->isNotEmpty())
            ->unique('schedule_slot_id')->values();
    }

    /** Pasa a un alumno de la lista de espera a un aula con lugar. */
    public function promote(Booking $booking, ?User $user): Booking
    {
        abort_unless($booking->is_waitlist && $booking->status->isActive(), 422);
        $school = School::findOrFail($booking->school_id);

        if (! $this->placeInRoom($school, $booking, $booking->date, $booking->slot) || $booking->is_waitlist) {
            throw ValidationException::withMessages(['booking' => __('agenda.validation.full')]);
        }

        $booking->save();
        $this->log($booking, 'promoted', $booking->placeLabel(), $user);

        return $booking;
    }

    private function log(Booking $booking, string $event, ?string $detail, ?User $user): void
    {
        BookingEvent::create(['school_id' => $booking->school_id, 'booking_id' => $booking->id, 'event' => $event, 'detail' => $detail,
            'user_id' => $user?->id, 'created_at' => now()]);
    }
}
