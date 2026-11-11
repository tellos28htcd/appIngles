<?php

namespace App\Actions\Agenda;

use App\Actions\Clubs\ScheduleClub;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\AttendanceEffect;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ClubModality;
use App\Enums\SelfBooking;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\CancellationReason;
use App\Models\Classroom;
use App\Models\ClubSession;
use App\Models\CoordinatorSession;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Notifications\BookingMoved;
use App\Support\ProgressPath;
use App\Support\RoomBalancer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agenda: lugares disponibles, reservación, cancelación, asistencia y lista de espera.
 *
 * - Práctica presencial: las horas del panel (teacher + aula física; sin clubs, revisiones, aulas virtuales
 *   ni el aula audiovisual); cupo por aula = "cupo por sesión" de Mi Instituto. Llenas, el alumno entra a la
 *   lista de espera (si hay columna "Lista de espera" o Mi Instituto lo permite).
 * - Práctica en línea: primero híbrida en un aula física (máximo 3 alumnos, a lo más 2 en línea); llenas,
 *   un aula virtual del catálogo ("alumnos por aula virtual").
 * - Balanceo por pesos (RoomBalancer): cada actividad pesa según el tipo de lección (catálogo de
 *   Actividades); el alumno va al aula de menor carga y, al agendar, cancelar o pasar de la lista de espera,
 *   se reacomodan los alumnos de esa hora (solo aula y teacher) hasta 3 minutos antes, con aviso al alumno.
 *   Las físicas e híbridas se balancean juntas; las virtuales entre ellas. La presentación (PR) es uno a uno.
 * - Tolerancia: recepción puede agendar una hora hasta 10 minutos después de que empezó (si hay cupo);
 *   el alumno solo antes de que empiece.
 * - Club: los clubs programados; cupo "por club" (presencial) o "por aula virtual" (en línea);
 *   nivel de la abreviatura, lección mínima para clubs y máximo de clubs por día.
 * - Por día: máximo de prácticas (Mi Instituto) y de horas según la asistencia de los últimos
 *   7 días (≥ 0.8 o sin historial: 2 horas; menos: 1).
 * - Cada práctica lleva la actividad que le toca (LLA) según la ruta de avance.
 * - Un teacher con cargo de Coordinador no recibe alumnos a la hora de una Evaluación de Club.
 * - En prácticas, el alumno no se acomoda con los teachers que excluyó en su expediente (hasta 3).
 */
final class AgendaService
{
    public const FULL_ATTENDANCE_HOURS = 2;

    public const LOW_ATTENDANCE_HOURS = 1;

    public const ATTENDANCE_THRESHOLD = 0.8;

    /** Minutos después del inicio de la hora en que recepción todavía puede agendar. */
    public const RECEPTION_TOLERANCE_MINUTES = 10;

    /** Minutos antes del inicio de la hora en que se deja de rebalancear las aulas. */
    public const REBALANCE_CUTOFF_MINUTES = 3;

    public function __construct(private AvailabilityPlanner $planner, private ScheduleClub $clubs) {}

    // ── Avance (LLA) ──────────────────────────────────────────────

    /**
     * Siguiente actividad del alumno: la que sigue a la última práctica reservada (no cancelada) o,
     * sin reservaciones, el punto de inicio de su ficha de inscripción.
     *
     * @return array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string}|null
     */
    public function nextActivity(Student $student, ?ProgressPath $path = null, int $offset = 0): ?array
    {
        $path ??= new ProgressPath($student->school);
        $last = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->active()
            ->where('kind', BookingKind::Session)->whereNotNull('lesson_id')->get(['lesson_id', 'activity_number'])
            ->map(fn (Booking $booking) => $path->nearestIndex($booking->lesson_id, $booking->activity_number, before: true))->filter(fn ($index) => $index !== null)->max();

        if ($last !== null) {
            return $path->at($last + 1 + $offset);
        }

        $enrollment = $student->enrollments()->withoutGlobalScope('school')->with('startActivity')->latest('registered_on')->latest('id')->first();
        $start = $path->nearestIndex($enrollment?->start_lesson_id, $enrollment?->startActivity?->number, before: false)
            ?? $path->firstOfLesson($enrollment?->start_lesson_id) ?? 0;

        return $path->at($start + $offset);
    }

    // ── Límites del alumno ────────────────────────────────────────

    /** Asistencia de los últimos 7 días (asistió ÷ asistió + faltó); null sin historial. */
    public function attendanceIndex(Student $student, CarbonInterface $today): ?float
    {
        $bookings = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->where('is_waitlist', false)
            ->whereBetween('date', [$today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString()])->with('cancellationReason')->get();

        $attended = $bookings->filter(fn (Booking $booking) => $booking->status === BookingStatus::Attended || $booking->cancellationReason?->attendance?->attendance() === true)->count();
        $absent = $bookings->filter(fn (Booking $booking) => $booking->cancellationReason?->attendance?->attendance() === false)->count();

        return $attended + $absent === 0 ? null : $attended / ($attended + $absent);
    }

    /** Horas que puede reservar por día según su asistencia (nuevo o ≥ 0.8: 2; menos: 1). */
    public function dailyLimit(Student $student, CarbonInterface $today): int
    {
        $index = $this->attendanceIndex($student, $today);

        return $index === null || $index >= self::ATTENDANCE_THRESHOLD ? self::FULL_ATTENDANCE_HOURS : self::LOW_ATTENDANCE_HOURS;
    }

    // ── Lugares ───────────────────────────────────────────────────

    /** Horas del panel con aula para práctica (sin clubs ni revisiones) a esa hora. @return Collection<int, TeacherAvailability> */
    private function sessionCells(School $school, CarbonInterface $date, ScheduleSlot $slot): Collection
    {
        $cells = TeacherAvailability::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->where('schedule_slot_id', $slot->id)->where('is_absent', false)->whereNotNull('classroom_id')
            ->whereNull('club_session_id')->whereNull('coordinator_session_id')
            ->with(['teacher:id,is_waitlist,short_name,first_name', 'classroom:id,name,capacity,is_virtual,is_club_room'])->get();

        return $this->withoutEvaluatingCoordinators($school, $cells, $date->toDateString(), $date->toDateString());
    }

    /**
     * Quita las horas de los teachers con cargo de Coordinador cuando a esa fecha y hora hay una
     * Evaluación de Club en Horas Coordinador: están evaluando, no pueden recibir alumnos.
     *
     * @param  Collection<int, TeacherAvailability>  $cells
     * @return Collection<int, TeacherAvailability>
     */
    private function withoutEvaluatingCoordinators(School $school, Collection $cells, string $from, string $to): Collection
    {
        $evaluations = CoordinatorSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereBetween('date', [$from, $to])
            ->with('activity')->get()->filter(fn (CoordinatorSession $session) => $session->activity?->evaluatesClubs())
            ->map(fn (CoordinatorSession $session) => $session->date->toDateString().'|'.$session->schedule_slot_id);

        if ($evaluations->isEmpty()) {
            return $cells;
        }

        $coordinators = Teacher::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereHas('user.role', fn ($role) => $role->where('slug', Role::ACADEMIC_COORDINATOR))->pluck('id');

        return $cells->reject(fn (TeacherAvailability $cell) => $coordinators->contains($cell->teacher_id)
            && $evaluations->contains($cell->date->toDateString().'|'.$cell->schedule_slot_id))->values();
    }

    /** Cupo de un aula física en práctica presencial: el "cupo de alumnos por sesión" de Mi Instituto (o el del aula si no hay). */
    private function cellCapacity(School $school, TeacherAvailability $cell): int
    {
        return max(1, (int) ($school->session_capacity ?: $cell->classroom?->capacity ?: 1));
    }

    /**
     * Aulas físicas de práctica a esa hora: las del panel sin la columna "Lista de espera", sin aulas virtuales y
     * sin el aula audiovisual (para clubs), que nunca recibe prácticas mientras esté marcada así.
     *
     * @param  Collection<int, TeacherAvailability>  $cells
     * @return Collection<int, TeacherAvailability>
     */
    private function physicalCells(Collection $cells): Collection
    {
        return $cells->reject(fn (TeacherAvailability $cell) => $cell->teacher?->is_waitlist || $cell->classroom?->is_virtual || $cell->classroom?->is_club_room)->values();
    }

    /** @return Collection<int, int> id de la celda => alumnos (sin lista de espera) */
    private function cellOccupancy(Collection $cells): Collection
    {
        return Booking::withoutGlobalScope('school')->active()->where('is_waitlist', false)->whereIn('teacher_availability_id', $cells->modelKeys())
            ->selectRaw('teacher_availability_id, count(*) as total')->groupBy('teacher_availability_id')->pluck('total', 'teacher_availability_id');
    }

    /**
     * Aulas físicas con lugar a esa hora; sin las de los teachers con los que el alumno no quiere practicar.
     *
     * @param  list<int>  $excludedTeacherIds
     * @return Collection<int, TeacherAvailability>
     */
    public function freeSessionCells(School $school, CarbonInterface $date, ScheduleSlot $slot, array $excludedTeacherIds = []): Collection
    {
        $state = $this->hourState($school, $date, $slot);
        $probe = ['room' => null, 'weight' => 0, 'online' => false, 'one_to_one' => false, 'movable' => true, 'excluded' => $excludedTeacherIds];
        $cells = $this->sessionCells($school, $date, $slot)->keyBy('id');

        return collect($state['rooms'])->filter(fn (array $room, string $key) => $room['group'] === 'physical'
            && RoomBalancer::seatsFor($room, RoomBalancer::itemsIn($state['items'], $key), $probe) > 0)
            ->map(fn (array $room) => $cells[$room['cell_id']])->values();
    }

    /** Teachers con los que el alumno no quiere hacer prácticas (su expediente). @return list<int> */
    public function excludedTeacherIds(?int $studentId): array
    {
        return $studentId === null ? [] : DB::table('student_excluded_teachers')->where('student_id', $studentId)
            ->pluck('teacher_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Lugares de una aula virtual: "Alumnos por aula virtual" de Mi Instituto (o la capacidad del aula). */
    private function virtualCapacity(School $school, Classroom $room): int
    {
        return max(1, (int) ($school->virtual_room_capacity ?: $room->capacity ?: $school->session_capacity ?: 1));
    }

    /**
     * Aulas virtuales que se usan para práctica a esa hora: las virtuales activas del catálogo que no
     * tienen un club en línea ni están reservadas; si Mi Instituto pone "Aulas virtuales por horario",
     * solo esa cantidad.
     *
     * @return Collection<int, Classroom>
     */
    private function practiceVirtualRooms(School $school, CarbonInterface $date, ScheduleSlot $slot): Collection
    {
        $rooms = $this->clubs->freeRooms($school, CarbonImmutable::parse($date->toDateString()), $slot, ClubModality::Online);

        return (int) $school->virtual_rooms_per_slot > 0 ? $rooms->take((int) $school->virtual_rooms_per_slot)->values() : $rooms;
    }

    /**
     * Teacher que atiende un aula virtual: el que el panel tenga en esa aula a esa hora; si no está
     * programada, uno de los que dan clase presencial a esa hora (el que lleve menos alumnos en línea).
     *
     * @param  list<int>  $excludedTeacherIds  teachers que no se eligen como respaldo
     */
    public function virtualTeacherId(School $school, CarbonInterface $date, ScheduleSlot $slot, int $classroomId, array $excludedTeacherIds = []): ?int
    {
        $cells = $this->sessionCells($school, $date, $slot)->reject(fn (TeacherAvailability $cell) => $cell->teacher?->is_waitlist);
        $assigned = $cells->firstWhere('classroom_id', $classroomId);

        if ($assigned !== null) {
            return $assigned->teacher_id;
        }

        $cells = $this->physicalCells($cells)->reject(fn (TeacherAvailability $cell) => in_array($cell->teacher_id, $excludedTeacherIds, true));

        $online = Booking::withoutGlobalScope('school')->active()->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->where('schedule_slot_id', $slot->id)->where('modality', ClubModality::Online)->whereNotNull('teacher_id')
            ->selectRaw('teacher_id, count(*) as total')->groupBy('teacher_id')->pluck('total', 'teacher_id');

        return $cells->sortBy(fn (TeacherAvailability $cell) => sprintf('%05d|%05d', $online[$cell->teacher_id] ?? 0, $cell->teacher_id))->first()?->teacher_id;
    }

    // ── Balanceo por pesos ────────────────────────────────────────

    /**
     * Aulas de práctica a esa hora y sus alumnos, para el balanceo (RoomBalancer): aulas físicas (con su
     * teacher del panel; con alumnos en línea son híbridas) y aulas virtuales (solo si hay teachers a esa hora).
     *
     * @return array{rooms: array<string, array{group: string, capacity: int, teacher_id: ?int, cell_id: ?int, classroom_id: int, label: string}>, items: array<int, array<string, mixed>>, bookings: Collection<int, Booking>}
     */
    private function hourState(School $school, CarbonInterface $date, ScheduleSlot $slot, ?int $exceptBookingId = null): array
    {
        $all = $this->sessionCells($school, $date, $slot);
        $cells = $this->physicalCells($all);
        $bookings = Booking::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->where('schedule_slot_id', $slot->id)->where('kind', BookingKind::Session)->where('is_waitlist', false)->active()
            ->when($exceptBookingId !== null, fn ($query) => $query->whereKeyNot($exceptBookingId))
            ->with(['lesson:id,type', 'classroom:id,name,is_virtual', 'student.user'])->get();

        $rooms = [];
        foreach ($cells as $cell) {
            $rooms['c'.$cell->id] = ['group' => 'physical', 'capacity' => $this->cellCapacity($school, $cell), 'teacher_id' => $cell->teacher_id,
                'cell_id' => $cell->id, 'classroom_id' => $cell->classroom_id, 'label' => __('agenda.room', ['name' => $cell->classroom?->name])];
        }

        if ($cells->isNotEmpty()) {
            foreach ($this->practiceVirtualRooms($school, $date, $slot) as $room) {
                // Teacher del aula virtual: el de sus alumnos, el del panel en esa aula o (vacía) se elige al acomodar.
                $teacherId = $bookings->first(fn (Booking $booking) => $booking->teacher_availability_id === null && $booking->classroom_id === $room->id && $booking->teacher_id)?->teacher_id
                    ?? $all->firstWhere('classroom_id', $room->id)?->teacher_id;
                $rooms['v'.$room->id] = ['group' => 'virtual', 'capacity' => $this->virtualCapacity($school, $room), 'teacher_id' => $teacherId,
                    'cell_id' => null, 'classroom_id' => $room->id, 'label' => __('agenda.virtual_classroom', ['name' => $room->name])];
            }
        }

        $excluded = DB::table('student_excluded_teachers')->whereIn('student_id', $bookings->pluck('student_id')->unique()->all())->get()
            ->groupBy('student_id')->map(fn (Collection $rows) => $rows->pluck('teacher_id')->map(fn ($id) => (int) $id)->all());

        $items = [];
        foreach ($bookings as $booking) {
            $items[$booking->id] = [...$this->itemFor($school, $booking, $excluded[$booking->student_id] ?? []), 'room' => $this->roomKeyOf($booking, $rooms)];
        }

        return ['rooms' => $rooms, 'items' => $items, 'bookings' => $bookings->keyBy('id')];
    }

    /**
     * Datos de una reservación para el balanceo: peso de su actividad según el tipo de lección (sin pesos
     * capturados cuenta 1 por alumno), si va en línea, si es uno a uno (PR) y si se puede mover.
     *
     * @param  list<int>  $excluded
     * @return array{room: ?string, weight: int, online: bool, one_to_one: bool, movable: bool, excluded: list<int>}
     */
    private function itemFor(School $school, Booking $booking, array $excluded): array
    {
        $catalog = Activity::weightsFor($school->id);
        $lesson = $booking->relationLoaded('lesson') ? $booking->lesson : Lesson::withoutGlobalScope('school')->find($booking->lesson_id);
        $weight = $lesson ? ($catalog['weights'][$lesson->type->value][$booking->activity_number] ?? null) : null;

        return [
            'room' => null,
            'weight' => $catalog['weights'] === [] ? 1 : (int) $weight,
            'online' => $booking->modality === ClubModality::Online,
            'one_to_one' => $weight !== null && in_array((int) $booking->activity_number, $catalog['one_to_one'], true),
            'movable' => $booking->status === BookingStatus::Booked,
            'excluded' => $excluded,
        ];
    }

    /** @param  array<string, array<string, mixed>>  $rooms */
    private function roomKeyOf(Booking $booking, array $rooms): ?string
    {
        $key = match (true) {
            $booking->teacher_availability_id !== null => 'c'.$booking->teacher_availability_id,
            $booking->classroom_id !== null => 'v'.$booking->classroom_id,
            default => null,
        };

        return $key !== null && isset($rooms[$key]) ? $key : null;
    }

    /**
     * Acomoda una práctica en el aula de menor carga que la acepta: primero las físicas (en línea = híbrida);
     * en línea, si están llenas, un aula virtual. Sin lugar, a la lista de espera si se permite (la presentación
     * uno a uno, que necesita un aula para ella sola, siempre espera).
     */
    private function placeSession(School $school, Booking $booking, CarbonInterface $date, ScheduleSlot $slot, bool $allowWaitlist = true): bool
    {
        $state = $this->hourState($school, $date, $slot, $booking->id);
        $item = $this->itemFor($school, $booking, $this->excludedTeacherIds($booking->student_id));

        foreach ($booking->modality === ClubModality::Online ? ['physical', 'virtual'] : ['physical'] as $group) {
            $rooms = array_filter($state['rooms'], fn (array $room) => $room['group'] === $group);
            $key = RoomBalancer::bestRoom($rooms, $state['items'], $item);

            if ($key !== null) {
                $this->assignRoom($school, $booking, $state['rooms'][$key], $date, $slot, $item['excluded']);

                return true;
            }
        }

        $cells = $this->sessionCells($school, $date, $slot);
        $waits = $item['one_to_one'] || ($booking->modality === ClubModality::InPerson && $this->waitlistAllowed($school, $cells));

        if (! $allowWaitlist || ! $waits) {
            return false;
        }

        // Aula de espera: la columna "Lista de espera" del panel (si la hay a esa hora).
        $waitlist = $cells->first(fn (TeacherAvailability $cell) => $cell->teacher?->is_waitlist);
        $booking->fill(['teacher_availability_id' => $waitlist?->id, 'teacher_id' => $waitlist?->teacher_id, 'classroom_id' => $waitlist?->classroom_id,
            'virtual_room' => null, 'is_waitlist' => true]);

        return true;
    }

    /**
     * @param  array{group: string, teacher_id: ?int, cell_id: ?int, classroom_id: int}  $room
     * @param  list<int>  $excluded
     */
    private function assignRoom(School $school, Booking $booking, array $room, CarbonInterface $date, ScheduleSlot $slot, array $excluded): void
    {
        $booking->fill([
            'teacher_availability_id' => $room['cell_id'], 'classroom_id' => $room['classroom_id'], 'is_waitlist' => false, 'virtual_room' => null,
            'teacher_id' => $room['teacher_id'] ?? $this->virtualTeacherId($school, $date, $slot, $room['classroom_id'], $excluded),
            // Aula virtual = en línea; un aula física conserva la modalidad del alumno (en línea = híbrida).
            'modality' => $room['group'] === 'virtual' ? ClubModality::Online : $booking->modality,
        ]);
    }

    /** ¿Todavía se balancea esa hora? Hasta REBALANCE_CUTOFF_MINUTES antes de que empiece (zona horaria de la escuela). */
    private function beforeRebalanceCutoff(School $school, CarbonInterface $date, ScheduleSlot $slot): bool
    {
        $timezone = $school->timezone ?: config('appingles.timezone');

        return CarbonImmutable::parse($date->toDateString().' '.$slot->starts_at, $timezone)->subMinutes(self::REBALANCE_CUTOFF_MINUTES)
            ->isAfter(CarbonImmutable::now($timezone));
    }

    /**
     * Rebalancea las aulas de esa hora: mueve (solo aula y teacher, misma fecha y hora) a los alumnos que se
     * pueden mover para que las cargas queden parejas. Cada cambio queda en el historial y se le avisa al alumno
     * en su cuenta. A los de $quiet (p. ej. el que acaba de agendar) no se les avisa.
     *
     * @param  list<int>  $quiet
     * @return int alumnos movidos
     */
    public function rebalance(School $school, CarbonInterface $date, ScheduleSlot $slot, ?User $user, array $quiet = []): int
    {
        if (! $this->beforeRebalanceCutoff($school, $date, $slot)) {
            return 0;
        }

        $state = $this->hourState($school, $date, $slot);
        $changes = RoomBalancer::rebalance($state['rooms'], $state['items']);

        foreach ($changes as $bookingId => $key) {
            $booking = $state['bookings'][$bookingId];
            $this->assignRoom($school, $booking, $state['rooms'][$key], $date, $slot, $state['items'][$bookingId]['excluded']);
            $booking->save();
            $booking->load('classroom');

            if (! in_array($bookingId, $quiet, true)) {
                $teacher = (string) Teacher::withoutGlobalScope('school')->find($booking->teacher_id)?->panelName();
                $this->log($booking, 'rebalanced', mb_substr($booking->placeLabel().' · '.$teacher, 0, 255), $user);
                $booking->student?->user?->notify(new BookingMoved($booking, $slot, $teacher));
            }
        }

        return count($changes);
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

    /**
     * Disponibilidad de la semana para el calendario, por "fecha|hora": lugares de práctica
     * presencial y en línea, si se puede agendar en lista de espera, clubs con sus lugares y
     * reservaciones. Todo en pocas consultas (no por celda).
     *
     * @param  list<CarbonImmutable>  $days
     * @return array<string, array{past: bool, session: array{free: int, capacity: int, in_person: int, online: int}, online: array{free: int, capacity: int}, waitlist: bool, waiting: int, booked: int, clubs: list<array{id: int, code: string, name: ?string, modality: ClubModality, color: int, free: int, capacity: int}>}>
     */
    public function weekAvailability(School $school, array $days, bool $byReception = false): array
    {
        $from = $days[0]->toDateString();
        $to = end($days)->toDateString();
        $cells = TeacherAvailability::withoutGlobalScope('school')->where('school_id', $school->id)->whereBetween('date', [$from, $to])
            ->where('is_absent', false)->whereNotNull('classroom_id')->whereNull('club_session_id')->whereNull('coordinator_session_id')
            ->with(['teacher:id,is_waitlist', 'classroom:id,capacity,is_virtual,is_club_room'])->get();
        $cells = $this->withoutEvaluatingCoordinators($school, $cells, $from, $to);
        $bookings = Booking::withoutGlobalScope('school')->where('school_id', $school->id)->whereBetween('date', [$from, $to])->active()
            ->get(['id', 'date', 'schedule_slot_id', 'kind', 'modality', 'teacher_availability_id', 'club_session_id', 'is_waitlist']);
        $clubs = ClubSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereBetween('date', [$from, $to])
            ->with(['club', 'classroom:id,capacity'])->get();

        $placed = $bookings->reject(fn (Booking $booking) => $booking->is_waitlist);
        $perCell = $placed->whereNotNull('teacher_availability_id')->countBy('teacher_availability_id');
        $onlinePerCell = $placed->whereNotNull('teacher_availability_id')->where('modality', ClubModality::Online)->countBy('teacher_availability_id');
        // Lugares de un aula física: su cupo; si ya es híbrida (con alumnos en línea), hasta 3 en total.
        $cellFree = fn (TeacherAvailability $cell) => max(0, ($onlinePerCell[$cell->id] ?? 0) > 0
            ? min(RoomBalancer::HYBRID_CAPACITY, $this->cellCapacity($school, $cell)) - ($perCell[$cell->id] ?? 0)
            : $this->cellCapacity($school, $cell) - ($perCell[$cell->id] ?? 0));
        $perClub = $placed->whereNotNull('club_session_id')->countBy('club_session_id');
        $key = fn ($model) => $model->date->toDateString().'|'.$model->schedule_slot_id;
        $bookingsAt = $bookings->groupBy($key);
        $clubsAt = $clubs->groupBy($key);
        $slots = ScheduleSlot::withoutGlobalScope('school')->where('school_id', $school->id)->get()->keyBy('id');
        $result = [];

        foreach ($cells->groupBy($key)->keys()->merge($clubsAt->keys())->merge($bookingsAt->keys())->unique() as $at) {
            [$date, $slotId] = explode('|', $at);
            $group = $cells->filter(fn (TeacherAvailability $cell) => $key($cell) === $at);
            $rooms = $this->physicalCells($group);
            $here = $bookingsAt->get($at, collect());
            $capacity = $rooms->sum(fn (TeacherAvailability $cell) => $this->cellCapacity($school, $cell));
            $online = $rooms->isNotEmpty() && isset($slots[$slotId])
                ? $this->practiceVirtualRooms($school, CarbonImmutable::parse($date), $slots[$slotId])->sum(fn (Classroom $room) => $this->virtualCapacity($school, $room))
                : 0;
            $sessions = $here->filter(fn (Booking $booking) => $booking->kind === BookingKind::Session && ! $booking->is_waitlist);
            // Aulas virtuales: solo los de en línea sin aula del panel (los híbridos ocupan lugar en el aula del teacher).
            $onlineTaken = $sessions->filter(fn (Booking $booking) => $booking->modality === ClubModality::Online && $booking->teacher_availability_id === null)->count();

            $result[$at] = [
                'past' => ! isset($slots[$slotId]) || ! $this->isFuture($school, CarbonImmutable::parse($date), $slots[$slotId], $byReception),
                'session' => [
                    'free' => (int) $rooms->sum($cellFree), 'capacity' => (int) $capacity,
                    'in_person' => $sessions->filter(fn (Booking $booking) => $booking->modality === ClubModality::InPerson)->count(),
                    'online' => $sessions->filter(fn (Booking $booking) => $booking->modality === ClubModality::Online)->count(),
                ],
                'online' => ['free' => max(0, $online - $onlineTaken), 'capacity' => $online],
                'waitlist' => $this->waitlistAllowed($school, $group),
                'waiting' => $here->filter(fn (Booking $booking) => $booking->is_waitlist)->count(),
                'booked' => $here->count(),
                'clubs' => $clubsAt->get($at, collect())->map(fn (ClubSession $club) => [
                    'id' => $club->id, 'code' => $club->code, 'name' => $club->club?->name, 'modality' => $club->modality, 'color' => $club->color(),
                    'free' => max(0, $this->clubCapacity($school, $club) - ($perClub[$club->id] ?? 0)), 'capacity' => $this->clubCapacity($school, $club),
                ])->values()->all(),
            ];
        }

        return $result;
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
     * Opciones de un día para el alumno: por hora, práctica presencial/en línea y clubs con lugar.
     *
     * @return list<array{slot: ScheduleSlot, options: list<array{kind: string, modality: string, club_session_id: ?int, label: string, free: int, waitlist: bool}>}>
     */
    public function options(School $school, Student $student, CarbonImmutable $date, bool $byReception = false): array
    {
        if (! $this->planner->isGenerated($school, $date->startOfWeek(CarbonInterface::MONDAY))) {
            return [];
        }

        $path = new ProgressPath($school);
        $step = $this->nextActivity($student, $path);
        $clubs = ClubSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())->with(['club', 'classroom'])->get();
        $excluded = $this->excludedTeacherIds($student->id);
        $result = [];

        foreach ($this->planner->slots($school) as $slot) {
            if (! $this->isFuture($school, $date, $slot, $byReception)) {
                continue;
            }

            $options = [];
            $cells = $this->sessionCells($school, $date, $slot);
            [$freeSeats, $onlineSeats, $oneToOne] = $step ? $this->seatsFor($school, $student, $step, $date, $slot, $excluded) : [0, 0, false];

            if ($freeSeats > 0) {
                $options[] = ['kind' => BookingKind::Session->value, 'modality' => ClubModality::InPerson->value, 'club_session_id' => null,
                    'label' => __('agenda.option_session'), 'free' => $freeSeats, 'waitlist' => false];
            } elseif ($step && ($oneToOne || $this->waitlistAllowed($school, $cells)) && $this->physicalCells($cells)->isNotEmpty()) {
                $options[] = ['kind' => BookingKind::Session->value, 'modality' => ClubModality::InPerson->value, 'club_session_id' => null,
                    'label' => __('agenda.option_session'), 'free' => 0, 'waitlist' => true];
            }

            // En línea: primero híbrida en un aula física con lugar y, llenas, un aula virtual.
            if ($onlineSeats > 0) {
                $options[] = ['kind' => BookingKind::Session->value, 'modality' => ClubModality::Online->value, 'club_session_id' => null,
                    'label' => __('agenda.option_session'), 'free' => $onlineSeats, 'waitlist' => false];
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

    /**
     * Lugares para el alumno a esa hora con su siguiente actividad: presenciales (aulas físicas) y en línea
     * (híbrida o aula virtual), según las reglas de aulas, y si su actividad es uno a uno (PR).
     *
     * @param  array{book_id: int, lesson_id: int, activity_number: int}  $step
     * @param  list<int>  $excluded
     * @return array{0: int, 1: int, 2: bool}
     */
    private function seatsFor(School $school, Student $student, array $step, CarbonInterface $date, ScheduleSlot $slot, array $excluded): array
    {
        $state = $this->hourState($school, $date, $slot);
        $probe = new Booking(['school_id' => $school->id, 'student_id' => $student->id, 'lesson_id' => $step['lesson_id'],
            'activity_number' => $step['activity_number'], 'kind' => BookingKind::Session, 'modality' => ClubModality::InPerson, 'status' => BookingStatus::Booked]);
        $inPerson = $this->itemFor($school, $probe, $excluded);
        $online = [...$inPerson, 'online' => true];
        $seats = fn (array $item, array $groups) => collect($state['rooms'])->filter(fn (array $room) => in_array($room['group'], $groups, true))
            ->sum(fn (array $room, string $key) => RoomBalancer::seatsFor($room, RoomBalancer::itemsIn($state['items'], $key), $item));

        return [$seats($inPerson, ['physical']), $seats($online, ['physical', 'virtual']), $inPerson['one_to_one']];
    }

    /**
     * ¿Todavía se puede agendar esa hora? (zona horaria de la escuela). El alumno, antes de que empiece;
     * recepción, hasta RECEPTION_TOLERANCE_MINUTES después de que empezó.
     */
    private function isFuture(School $school, CarbonInterface $date, ScheduleSlot $slot, bool $byReception = false): bool
    {
        $timezone = $school->timezone ?: config('appingles.timezone');
        $limit = CarbonImmutable::parse($date->toDateString().' '.$slot->starts_at, $timezone)
            ->addMinutes($byReception ? self::RECEPTION_TOLERANCE_MINUTES : 0);

        return $limit->isAfter(CarbonImmutable::now($timezone));
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

        if (! $this->isFuture($school, $date, $slot, $byReception)) {
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

            $booking = new Booking([
                'school_id' => $school->id, 'student_id' => $student->id, 'date' => $date->toDateString(), 'schedule_slot_id' => $slot->id,
                'kind' => $kind, 'modality' => $modality, 'status' => BookingStatus::Booked, 'booked_by_reception' => $byReception, 'user_id' => $user?->id,
            ]);

            $this->place($school, $student, $date, $slot, $kind, $modality, $clubSessionId, $booking, $fail);

            $booking->save();

            if ($kind === BookingKind::Session && ! $booking->is_waitlist && $this->rebalance($school, $date, $slot, $user, quiet: [$booking->id]) > 0) {
                $booking->refresh();
            }

            $this->log($booking, $booking->is_waitlist ? 'waitlisted' : 'booked', $booking->placeLabel(), $user);

            return $booking;
        });
    }

    /**
     * Lugar de una reservación según su tipo: club (con su cupo y nivel) o práctica con su actividad (LLA),
     * presencial (aula al azar o lista de espera) o en línea (aula virtual o híbrida).
     */
    private function place(School $school, Student $student, CarbonImmutable $date, ScheduleSlot $slot, BookingKind $kind, ClubModality $modality,
        ?int $clubSessionId, Booking $booking, Closure $fail): void
    {
        $path = new ProgressPath($school);

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

            $booking->student_id ??= $student->id;
            $this->placeSession($school, $booking, $date, $slot) || $fail('full');
        }
    }

    /**
     * ¿Se agenda en lista de espera cuando las aulas se llenan? Sí si el panel tiene a esa hora la
     * columna "Lista de espera" (su aula de espera) o si Mi Instituto lo permite.
     *
     * @param  Collection<int, TeacherAvailability>  $cells
     */
    private function waitlistAllowed(School $school, Collection $cells): bool
    {
        return $cells->contains(fn (TeacherAvailability $cell) => (bool) $cell->teacher?->is_waitlist)
            || ((bool) $school->books_without_classroom && $cells->isNotEmpty());
    }

    // ── Cambiar aula, teacher o alumno ────────────────────────────

    /**
     * Recepción cambia una reservación: otra aula disponible a esa hora (el teacher es el que da clase en
     * esa aula según el panel) y/o pasa el lugar a otro alumno (con sus validaciones y su siguiente
     * actividad). En los clubs solo se cambia el alumno.
     */
    public function change(Booking $booking, ?int $classroomId, ?Student $student, ?User $user): Booking
    {
        abort_unless($booking->canBeChanged(), 422);
        $fail = fn (string $key, array $replace = []) => throw ValidationException::withMessages(['booking' => __("agenda.validation.$key", $replace)]);
        $school = School::findOrFail($booking->school_id);
        $timezone = $school->timezone ?: config('appingles.timezone');

        return DB::transaction(function () use ($booking, $classroomId, $student, $user, $fail, $school, $timezone): Booking {
            $changes = [];

            if ($student !== null && $student->id !== $booking->student_id) {
                abort_unless($student->school_id === $booking->school_id, 404);
                $day = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->whereDate('date', $booking->date->toDateString())
                    ->active()->lockForUpdate()->get();

                $day->contains('schedule_slot_id', $booking->schedule_slot_id) && $fail('duplicate');
                $booking->kind === BookingKind::Session && $booking->teacher_id && in_array($booking->teacher_id, $this->excludedTeacherIds($student->id), true)
                    && $fail('excluded_teacher', ['teacher' => Teacher::withoutGlobalScope('school')->find($booking->teacher_id)?->panelName() ?? '—']);
                $limit = $this->dailyLimit($student, CarbonImmutable::now($timezone)->startOfDay());
                $day->count() >= $limit && $fail('daily_limit', ['hours' => $limit]);

                $path = new ProgressPath($school);
                if ($booking->kind === BookingKind::Club) {
                    $booking->clubSession && ! $this->clubAllows($school, $booking->clubSession, $this->nextActivity($student, $path), $path) && $fail('club_not_allowed');
                } else {
                    $step = $this->nextActivity($student, $path) ?? $fail('no_activity');
                    $booking->fill(['book_id' => $step['book_id'], 'lesson_id' => $step['lesson_id'], 'activity_number' => $step['activity_number']]);
                }

                $booking->student_id = $student->id;
                $changes[] = $student->fullName();
            }

            if ($booking->kind === BookingKind::Session && $classroomId !== null && $classroomId !== $booking->classroom_id) {
                $room = $this->roomChoices($booking)[$classroomId] ?? $fail('room_full', ['room' => Classroom::withoutGlobalScope('school')->find($classroomId)?->name ?? '—']);

                $booking->fill([
                    'classroom_id' => $classroomId, 'teacher_id' => $room['teacher_id'], 'teacher_availability_id' => $room['cell_id'],
                    'is_waitlist' => false, 'virtual_room' => null,
                    // Aula virtual = en línea; un aula física conserva la modalidad que eligió el alumno (en línea = híbrida).
                    'modality' => $room['is_virtual'] ? ClubModality::Online : $booking->modality,
                ]);
                $booking->setRelation('classroom', Classroom::withoutGlobalScope('school')->find($classroomId));
                $changes[] = $booking->placeLabel().' · '.$room['teacher'];
            }

            if ($changes === []) {
                return $booking;
            }

            $booking->save();
            $this->log($booking, 'changed', mb_substr(implode(' · ', $changes), 0, 255), $user);

            return $booking;
        });
    }

    /**
     * El alumno llegó a otra actividad (p. ej. reservó club y viene a práctica): la reservación cambia a la
     * opción elegida de esa misma hora si hay lugar (libera su lugar actual y se vuelve a acomodar).
     */
    public function changeActivity(Booking $booking, BookingKind $kind, ClubModality $modality, ?int $clubSessionId, ?User $user): Booking
    {
        abort_unless($booking->canBeChanged(), 422);
        $fail = fn (string $key, array $replace = []) => throw ValidationException::withMessages(['booking' => __("agenda.validation.$key", $replace)]);
        $school = School::findOrFail($booking->school_id);

        return DB::transaction(function () use ($booking, $kind, $modality, $clubSessionId, $user, $fail, $school): Booking {
            $student = Student::withoutGlobalScope('school')->findOrFail($booking->student_id);
            $day = Booking::withoutGlobalScope('school')->where('student_id', $student->id)->whereDate('date', $booking->date->toDateString())
                ->active()->whereKeyNot($booking->id)->lockForUpdate()->get();

            if ($kind === BookingKind::Session && $school->max_sessions && $day->where('kind', BookingKind::Session)->count() >= $school->max_sessions) {
                $fail('max_sessions', ['max' => $school->max_sessions]);
            }
            if ($kind === BookingKind::Club && $school->max_clubs && $day->where('kind', BookingKind::Club)->count() >= $school->max_clubs) {
                $fail('max_clubs', ['max' => $school->max_clubs]);
            }

            $before = $booking->kind->label().' '.$booking->activityLabel();
            // Libera su lugar mientras se acomoda de nuevo (si no hay lugar, la transacción lo deja como estaba).
            $booking->update(['status' => BookingStatus::Cancelled]);
            $booking->fill([
                'status' => BookingStatus::Booked, 'kind' => $kind, 'modality' => $modality,
                'teacher_availability_id' => null, 'club_session_id' => null, 'teacher_id' => null, 'classroom_id' => null, 'virtual_room' => null,
                'is_waitlist' => false, 'book_id' => null, 'lesson_id' => null, 'activity_number' => null,
            ]);
            $this->place($school, $student, CarbonImmutable::parse($booking->date->toDateString()), $booking->slot, $kind, $modality, $clubSessionId, $booking, $fail);
            $booking->save();
            $this->rebalance($school, $booking->date, $booking->slot, $user, quiet: [$booking->id]) > 0 && $booking->refresh();
            $booking->unsetRelation('classroom')->unsetRelation('clubSession')->unsetRelation('book')->unsetRelation('lesson');
            $this->log($booking, 'activity_changed', mb_substr($before.' → '.$booking->kind->label().' '.$booking->activityLabel().' · '.$booking->placeLabel(), 0, 255), $user);

            return $booking;
        });
    }

    /** Asistencia marcada por error: regresa a Reservada. */
    public function undoAttendance(Booking $booking, ?User $user): void
    {
        abort_unless($booking->status === BookingStatus::Attended, 422);

        $booking->update(['status' => BookingStatus::Booked]);
        $this->log($booking, 'attendance_undone', null, $user);
    }

    /**
     * Aulas a las que se puede mover una práctica a esa hora: las del panel (con su teacher) con lugar
     * y las aulas virtuales con lugar (con el teacher que las atiende). Incluye la actual.
     *
     * @return array<int, array{label: string, teacher: string, teacher_id: ?int, cell_id: ?int, is_virtual: bool, free: int}>
     */
    public function roomChoices(Booking $booking): array
    {
        $school = School::findOrFail($booking->school_id);
        $slot = $booking->slot;
        $state = $this->hourState($school, $booking->date, $slot, $booking->id);
        $excluded = $this->excludedTeacherIds($booking->student_id);
        $item = $this->itemFor($school, $booking, $excluded);
        $current = $booking->teacher_availability_id !== null ? 'c'.$booking->teacher_availability_id : ($booking->classroom_id ? 'v'.$booking->classroom_id : null);
        $choices = [];

        $rooms = collect($state['rooms'])->sortBy(fn (array $room) => ($room['group'] === 'virtual' ? '1' : '0').sprintf('%05d', (int) preg_replace('/\D/', '', $room['label'])).$room['label']);

        foreach ($rooms as $key => $room) {
            // En un aula virtual el alumno va en línea.
            $probe = [...$item, 'online' => $item['online'] || $room['group'] === 'virtual', 'excluded' => $key === $current ? [] : $excluded];
            $free = RoomBalancer::seatsFor($room, RoomBalancer::itemsIn($state['items'], $key), $probe);

            if ($free > 0 || $key === $current) {
                $teacherId = $key === $current && $booking->teacher_id ? $booking->teacher_id
                    : ($room['teacher_id'] ?? $this->virtualTeacherId($school, $booking->date, $slot, $room['classroom_id'], $excluded));
                $choices[$room['classroom_id']] = [
                    'label' => $room['label'], 'teacher' => (string) Teacher::withoutGlobalScope('school')->find($teacherId)?->panelName(),
                    'teacher_id' => $teacherId, 'cell_id' => $room['cell_id'], 'is_virtual' => $room['group'] === 'virtual', 'free' => max(0, $free),
                ];
            }
        }

        return $choices;
    }
    // ── Cancelar, asistencia y lista de espera ────────────────────

    /** Motivo que se usa cuando el alumno cancela él mismo: el primero activo que "no cuenta" para la asistencia. */
    public function studentCancellationReason(int $schoolId): ?CancellationReason
    {
        return CancellationReason::withoutGlobalScope('school')->where('school_id', $schoolId)->where('is_active', true)
            ->where('attendance', AttendanceEffect::None)->orderBy('sort_order')->orderBy('id')->first();
    }

    public function cancel(Booking $booking, ?CancellationReason $reason, ?User $user): void
    {
        abort_if($booking->status === BookingStatus::Cancelled, 422);

        $booking->update(['status' => BookingStatus::Cancelled, 'cancellation_reason_id' => $reason?->id, 'cancelled_at' => now()]);
        $this->log($booking, 'cancelled', $reason?->name, $user);

        if ($booking->kind === BookingKind::Session && ! $booking->is_waitlist && $booking->slot) {
            $this->rebalance(School::findOrFail($booking->school_id), $booking->date, $booking->slot, $user);
        }
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

        if (! $this->placeSession($school, $booking, $booking->date, $booking->slot, allowWaitlist: false)) {
            throw ValidationException::withMessages(['booking' => __('agenda.validation.full')]);
        }

        $booking->save();
        $this->rebalance($school, $booking->date, $booking->slot, $user, quiet: [$booking->id]) > 0 && $booking->refresh();
        $this->log($booking, 'promoted', $booking->placeLabel(), $user);

        return $booking;
    }

    /**
     * Alumnos en lista de espera a la misma hora que una reservación (los que pueden tomar su lugar).
     *
     * @return Collection<int, Booking>
     */
    public function waitlistAt(Booking $booking): Collection
    {
        return Booking::withoutGlobalScope('school')->where('school_id', $booking->school_id)->whereDate('date', $booking->date->toDateString())
            ->where('schedule_slot_id', $booking->schedule_slot_id)->where('kind', BookingKind::Session)->where('is_waitlist', true)->active()
            ->whereKeyNot($booking->id)->with('student')->orderBy('created_at')->orderBy('id')->get()
            // No se le pasa el lugar a quien no quiere practicar con el teacher de esa aula.
            ->reject(fn (Booking $waiting) => $booking->teacher_id && in_array($booking->teacher_id, $this->excludedTeacherIds($waiting->student_id), true))
            ->values();
    }

    /**
     * El alumno de la reservación no llegó o canceló: su reservación se cancela con el motivo y el alumno
     * de la lista de espera (de ese día y hora) toma su lugar (aula y teacher).
     */
    public function giveSeat(Booking $booking, Booking $waiting, ?CancellationReason $reason, ?User $user): Booking
    {
        abort_unless($booking->canBeChanged() && ! $booking->is_waitlist, 422);
        abort_unless($this->waitlistAt($booking)->contains('id', $waiting->id), 404);

        return DB::transaction(function () use ($booking, $waiting, $reason, $user): Booking {
            $this->cancel($booking, $reason, $user);

            $room = $booking->classroom()->first();
            $waiting->fill([
                'classroom_id' => $booking->classroom_id, 'teacher_id' => $booking->teacher_id,
                'teacher_availability_id' => $booking->teacher_availability_id, 'virtual_room' => $booking->virtual_room, 'is_waitlist' => false,
                'modality' => $room?->is_virtual ? ClubModality::Online : $waiting->modality,
            ])->save();
            $waiting->setRelation('classroom', $room);
            $this->log($waiting, 'promoted', $waiting->placeLabel(), $user);
            $this->rebalance(School::findOrFail($waiting->school_id), $waiting->date, $booking->slot, $user, quiet: [$waiting->id]);

            return $waiting->refresh();
        });
    }

    private function log(Booking $booking, string $event, ?string $detail, ?User $user): void
    {
        BookingEvent::create(['school_id' => $booking->school_id, 'booking_id' => $booking->id, 'event' => $event, 'detail' => $detail,
            'user_id' => $user?->id, 'created_at' => now()]);
    }
}
