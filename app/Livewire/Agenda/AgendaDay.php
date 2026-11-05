<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\AgendaService;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\AttendanceEffect;
use App\Enums\BookingKind;
use App\Enums\ClubModality;
use App\Enums\StudentStatus;
use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\Classroom;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Agenda del día (recepción): por hora, las reservaciones ordenadas por aula → teacher →
 * alumno con su actividad o club. Aquí se agenda (eligiendo al alumno; el aula la asigna
 * el sistema), se cancela con motivo, se marca asistencia y se pasa a la lista de espera a un aula.
 */
#[Layout('layouts.app')]
class AgendaDay extends Component
{
    #[Session]
    public string $date = '';

    /** Calendario semanal oculto para ver solo el listado del día (se recuerda en la sesión). */
    #[Session]
    public bool $calendarCollapsed = false;

    public ?bool $creating = null;

    /** Hora elegida en el calendario (la reservación se limita a esa hora). */
    public ?int $slotId = null;

    public string $search = '';

    public ?int $studentId = null;

    /** Opción elegida: "slot|kind|modality|club_session_id". */
    public string $option = '';

    public ?int $cancellingId = null;

    public ?int $reasonId = null;

    /** Filtros del listado del día (hora, aula y teacher); se recuerdan en la sesión. */
    #[Session]
    public string $filterSlot = '';

    #[Session]
    public string $filterRoom = '';

    #[Session]
    public string $filterTeacher = '';

    /** Reservación que se edita (recepción): aula (con su teacher) y alumno. */
    public ?int $editingId = null;

    public ?int $editRoomId = null;

    /** Alumno de la lista de espera (misma hora) que toma el lugar, y motivo para el que sale. */
    public ?int $editWaitingId = null;

    public ?int $editReasonId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);

        if (! $this->validDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }
    }

    public function updatedFilterSlot(): void
    {
        unset($this->bookings);
    }

    public function clearFilters(): void
    {
        $this->reset('filterSlot', 'filterRoom', 'filterTeacher');
        unset($this->bookings);
    }

    public function updatedDate(): void
    {
        if (! $this->validDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }
        unset($this->bookings, $this->proposals);
    }

    public function shiftDay(int $days): void
    {
        $this->date = $this->day()->addDays(max(-1, min(1, $days)))->toDateString();
        unset($this->bookings, $this->proposals);
    }

    /** Semana anterior o siguiente (conserva el día de la semana elegido). */
    public function shiftWeek(int $weeks): void
    {
        $this->date = $this->day()->addWeeks(max(-1, min(1, $weeks)))->toDateString();
        unset($this->bookings, $this->proposals);
    }

    public function toggleCalendar(): void
    {
        $this->calendarCollapsed = ! $this->calendarCollapsed;
    }

    /** Clic en el encabezado del día: muestra su listado. */
    public function selectDay(string $date): void
    {
        if ($this->validDate($date)) {
            $this->date = $date;
            unset($this->bookings, $this->proposals);
        }
    }

    /** Clic en una celda del calendario: agenda a un alumno en ese día y hora. */
    public function openSlot(string $date, int $slotId): void
    {
        $this->authorize('manage', Booking::class);
        $this->selectDay($date);
        $this->create();
        $this->slotId = ScheduleSlot::query()->findOrFail($slotId)->id;
    }

    public function goToday(): void
    {
        $this->date = $this->today()->toDateString();
        unset($this->bookings, $this->proposals);
    }

    // ── Nueva reservación ─────────────────────────────────────────

    public function create(): void
    {
        $this->authorize('manage', Booking::class);
        $this->reset('search', 'studentId', 'option', 'slotId');
        $this->resetValidation();
        $this->creating = true;
    }

    public function pickStudent(int $studentId): void
    {
        $this->authorize('manage', Booking::class);
        $this->studentId = Student::query()->findOrFail($studentId)->id;
        $this->option = '';
        unset($this->options);
    }

    public function clearStudent(): void
    {
        $this->reset('studentId', 'option');
        unset($this->options);
    }

    public function book(AgendaService $agenda): void
    {
        $this->authorize('manage', Booking::class);
        $student = Student::query()->findOrFail($this->studentId);
        $choice = $this->parseOption($this->option);

        try {
            $booking = $agenda->book($this->school(), $student, $this->day(), $choice['slot'], $choice['kind'], $choice['modality'],
                $choice['club_session_id'], auth()->user(), byReception: true);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());

            return;
        }

        $this->creating = null;
        unset($this->bookings, $this->options);
        $this->dispatch('toast', type: $booking->is_waitlist ? 'warning' : 'success', message: $booking->is_waitlist
            ? __('agenda.messages.waitlisted')
            : __('agenda.messages.booked', ['place' => $booking->placeLabel()]));
    }

    // ── Cancelar, asistencia y lista de espera ────────────────────

    public function confirmCancel(int $bookingId): void
    {
        $this->authorize('manage', Booking::class);
        $this->cancellingId = $this->findBooking($bookingId)->id;
        $this->reasonId = app(AgendaService::class)->studentCancellationReason(auth()->user()->school_id)?->id;
    }

    public function cancel(AgendaService $agenda): void
    {
        $this->authorize('manage', Booking::class);
        $this->validate(['reasonId' => ['required', 'integer', Rule::in($this->reasons()->modelKeys())]], [], ['reasonId' => Str::lower(__('agenda.form.reason'))]);

        $agenda->cancel($this->findBooking($this->cancellingId), $this->reasons()->find($this->reasonId), auth()->user());

        $this->cancellingId = null;
        unset($this->bookings, $this->proposals);
        $this->dispatch('toast', type: 'success', message: __('agenda.messages.cancelled'));
    }

    public function markAttended(int $bookingId, AgendaService $agenda): void
    {
        $this->authorize('manage', Booking::class);
        $agenda->markAttended($this->findBooking($bookingId), auth()->user());

        unset($this->bookings);
        $this->dispatch('toast', type: 'success', message: __('agenda.messages.attended'));
    }

    public function promote(int $bookingId, AgendaService $agenda): void
    {
        $this->authorize('manage', Booking::class);

        try {
            $booking = $agenda->promote($this->findBooking($bookingId), auth()->user());
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());

            return;
        }

        unset($this->bookings, $this->proposals);
        $this->dispatch('toast', type: 'success', message: __('agenda.messages.promoted', ['place' => $booking->placeLabel()]));
    }

    // ── Cambiar aula, teacher o alumno ────────────────────────────

    public function editBooking(int $bookingId): void
    {
        $this->authorize('manage', Booking::class);
        $booking = $this->findBooking($bookingId);
        abort_unless($booking->canBeChanged(), 422);

        $this->editingId = $booking->id;
        $this->editRoomId = $booking->classroom_id;
        $this->editWaitingId = null;
        $this->editReasonId = $this->reasons()->first(fn (CancellationReason $reason) => $reason->attendance === AttendanceEffect::Absent)?->id;
        $this->resetValidation();
    }

    public function saveChange(AgendaService $agenda): void
    {
        $this->authorize('manage', Booking::class);
        $booking = $this->findBooking($this->editingId);

        try {
            $booking = $agenda->change($booking, $this->editRoomId, null, auth()->user());

            if ($this->editWaitingId !== null) {
                $this->validate(['editReasonId' => ['required', 'integer', Rule::in($this->reasons()->modelKeys())]], [], ['editReasonId' => Str::lower(__('agenda.form.reason'))]);
                $agenda->giveSeat($booking, $this->findBooking($this->editWaitingId), $this->reasons()->find($this->editReasonId), auth()->user());
            }
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());

            return;
        }

        $this->editingId = null;
        unset($this->bookings, $this->proposals);
        $this->dispatch('toast', type: 'success', message: __('agenda.messages.changed'));
    }

    /**
     * Ventana de edición: aulas disponibles a esa hora (con lugar, más la actual), cada una con el teacher
     * que da la clase ahí; el teacher no se elige, sale del aula.
     *
     * @return array{rooms: array<int, string>, choices: array<int, array<string, mixed>>, waiting: Collection<int, Booking>, booking: ?Booking}
     */
    private function editData(): array
    {
        $booking = $this->editingId ? Booking::query()->with(['slot', 'clubSession', 'student'])->find($this->editingId) : null;

        if ($booking === null) {
            return ['rooms' => [], 'choices' => [], 'waiting' => collect(), 'booking' => null];
        }

        $choices = $booking->kind === BookingKind::Session ? app(AgendaService::class)->roomChoices($booking) : [];

        return [
            'booking' => $booking,
            'waiting' => $booking->kind === BookingKind::Session && ! $booking->is_waitlist ? app(AgendaService::class)->waitlistAt($booking) : collect(),
            'choices' => $choices,
            'rooms' => collect($choices)->map(fn (array $room) => $room['label'].($room['teacher'] !== '' ? ' · '.$room['teacher'] : '')
                .' ('.trans_choice('agenda.seats', $room['free']).')')->all(),
        ];
    }

    // ── Datos ─────────────────────────────────────────────────────

    /**
     * Reservaciones del día agrupadas por hora; dentro, ordenadas por aula → teacher → alumno.
     *
     * @return Collection<int, array{slot: ScheduleSlot, bookings: Collection<int, Booking>}>
     */
    #[Computed]
    public function bookings(): Collection
    {
        $bookings = Booking::query()->whereDate('date', $this->date)
            ->with(['student', 'slot', 'teacher', 'classroom', 'book', 'lesson', 'clubSession.club', 'events.user', 'cancellationReason'])
            ->get();

        $bookings = $bookings
            ->when($this->filterSlot !== '', fn (Collection $items) => $items->where('schedule_slot_id', (int) $this->filterSlot))
            ->when($this->filterRoom !== '', fn (Collection $items) => $items->where('classroom_id', (int) $this->filterRoom))
            ->when($this->filterTeacher !== '', fn (Collection $items) => $items->where('teacher_id', (int) $this->filterTeacher));

        return $bookings->groupBy('schedule_slot_id')
            ->map(fn (Collection $items) => [
                'slot' => $items->first()->slot,
                'bookings' => $items->sortBy(fn (Booking $booking) => sprintf('%d|%s|%s|%s',
                    $booking->status->isActive() ? 0 : 1, $booking->is_waitlist ? 'zz' : $booking->placeLabel(),
                    $booking->teacher?->panelName() ?? 'zz', $booking->student?->fullName()))->values(),
            ])
            ->sortBy(fn (array $group) => $group['slot']?->starts_at)
            ->values();
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function proposals(): Collection
    {
        return app(AgendaService::class)->waitlistProposals($this->school(), $this->day());
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function students(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Student::query()->search($this->search)->where('status', StudentStatus::Active)
            ->orderBy('first_name')->orderBy('last_name')->limit(8)->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function options(): array
    {
        $student = $this->studentId ? Student::query()->find($this->studentId) : null;

        $options = $student ? app(AgendaService::class)->options($this->school(), $student, $this->day(), byReception: true) : [];

        return $this->slotId === null ? $options : array_values(array_filter($options, fn (array $hour) => $hour['slot']->id === $this->slotId));
    }

    #[Computed]
    public function selectedStudent(): ?Student
    {
        return $this->studentId ? Student::query()->find($this->studentId) : null;
    }

    public function render(AgendaService $agenda): View
    {
        $school = $this->school();
        $planner = app(AvailabilityPlanner::class);
        $days = $planner->days($school, $this->day());
        $isReady = $planner->isGenerated($school, $days[0]);

        // Filtros con lo que hay ese día; color de cada aula como en el panel (por su número).
        $dayBookings = Booking::query()->whereDate('date', $this->date)->with(['slot', 'classroom', 'teacher'])->get();
        $classrooms = Classroom::query()->ofCatalog($school->id)->orderByRaw('CAST(name AS UNSIGNED)')->orderBy('name')->get(['id', 'name']);

        return view('livewire.agenda.agenda-day', [
            'filterSlots' => $dayBookings->pluck('slot')->filter()->unique('id')->sortBy('starts_at')->mapWithKeys(fn (ScheduleSlot $slot) => [$slot->id => $slot->range()])->all(),
            'filterRooms' => $dayBookings->pluck('classroom')->filter()->unique('id')->sortBy(fn (Classroom $room) => sprintf('%05d', (int) $room->name).$room->name)
                ->mapWithKeys(fn (Classroom $room) => [$room->id => $room->name])->all(),
            'filterTeachers' => $dayBookings->pluck('teacher')->filter()->unique('id')->sortBy(fn (Teacher $teacher) => $teacher->panelName())
                ->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->panelName()])->all(),
            'roomNumbers' => $classrooms->values()->mapWithKeys(fn (Classroom $room, int $index) => [$room->id => ctype_digit((string) $room->name) ? (int) $room->name : $index + 1])->all(),
            'edit' => $this->editData(),
            'canManage' => auth()->user()->can('manage', Booking::class),
            'day' => $this->day(),
            'days' => $days,
            'planner' => $planner,
            'scheduleSlots' => $planner->slots($school),
            'holidays' => $planner->holidays($school, $days),
            'availability' => $isReady ? $agenda->weekAvailability($school, $days, byReception: true) : [],
            'slotLabel' => $this->slotId ? ScheduleSlot::query()->find($this->slotId)?->range() : null,
            'isReady' => $isReady,
            'reasonOptions' => $this->reasons()->pluck('name', 'id')->all(),
            'nextActivity' => $this->selectedStudent ? $agenda->nextActivity($this->selectedStudent) : null,
            'dailyLimit' => $this->selectedStudent ? $agenda->dailyLimit($this->selectedStudent, $this->today()) : null,
        ])->title(__('agenda.title'));
    }

    // ── Apoyo ─────────────────────────────────────────────────────

    /** @return array{slot: ScheduleSlot, kind: BookingKind, modality: ClubModality, club_session_id: ?int} */
    private function parseOption(string $option): array
    {
        [$slotId, $kind, $modality, $clubId] = array_pad(explode('|', $option), 4, '');
        $slot = ScheduleSlot::query()->find((int) $slotId);
        $kind = BookingKind::tryFrom($kind);
        $modality = ClubModality::tryFrom($modality);

        if (! $slot || ! $kind || ! $modality) {
            throw ValidationException::withMessages(['option' => __('agenda.validation.option')]);
        }

        return ['slot' => $slot, 'kind' => $kind, 'modality' => $modality, 'club_session_id' => $clubId === '' ? null : (int) $clubId];
    }

    /** Motivos de cancelación activos del catálogo de la escuela. @return \Illuminate\Database\Eloquent\Collection<int, CancellationReason> */
    private function reasons(): \Illuminate\Database\Eloquent\Collection
    {
        return CancellationReason::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function findBooking(?int $bookingId): Booking
    {
        return Booking::query()->findOrFail($bookingId);
    }

    private function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->school()->timezone ?: config('appingles.timezone'))->startOfDay();
    }

    private function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date);
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false;
    }
}
