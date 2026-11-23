<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\AgendaService;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\BookingKind;
use App\Livewire\Agenda\Concerns\ChoosesBookingOptions;
use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Mi agenda (alumno): calendario semanal sin números de cupo (las horas llenas en tono tenue); clic en una celda
 * para reservar esa hora o "Nueva reservación" para elegir hasta 2 horas de un día. Solo práctica y club, y desde
 * el día siguiente (el mismo día, con Recepción). El sistema le asigna el aula (o la lista de espera). Cancela con
 * un motivo de su menú. Desde "Agendar club" solo se reservan clubs.
 */
#[Layout('layouts.app')]
class MyAgenda extends Component
{
    use ChoosesBookingOptions;

    /** Semanas hacia adelante que puede consultar. */
    public const WEEKS_AHEAD = 2;

    #[Locked]
    public bool $clubsOnly = false;

    /** Lunes de la semana que se muestra. */
    public string $week = '';

    public string $date = '';

    public ?int $slotId = null;

    public ?bool $choosing = null;

    /** Reservación que el alumno cancela (con el motivo que elige). */
    public ?int $cancellingId = null;

    public ?int $cancelReasonId = null;

    public function mount(): void
    {
        $this->authorize('bookOwn', Booking::class);
        $this->clubsOnly = request()->routeIs('my-agenda.clubs');
        $this->week = $this->today()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
    }

    public function shiftWeek(int $weeks): void
    {
        $current = $this->today()->startOfWeek(CarbonImmutable::MONDAY);
        $target = CarbonImmutable::parse($this->week)->addWeeks(max(-1, min(1, $weeks)));

        if ($target->gte($current) && $target->lte($current->addWeeks(self::WEEKS_AHEAD))) {
            $this->week = $target->toDateString();
        }
    }

    /** Clic en una celda del calendario: opciones de esa hora (desde el día siguiente). */
    public function openSlot(string $date, int $slotId): void
    {
        $this->authorize('bookOwn', Booking::class);
        $day = CarbonImmutable::parse($date);
        abort_unless($day->startOfWeek(CarbonImmutable::MONDAY)->toDateString() === $this->week, 404);

        if ($day->lte($this->today())) {
            $this->dispatch('toast', type: 'warning', message: __('agenda.validation.day_before'));

            return;
        }

        $this->date = $day->toDateString();
        $this->slotId = ScheduleSlot::query()->findOrFail($slotId)->id;
        $this->choices = [];
        $this->choosing = true;
        unset($this->options);
    }

    /** "Nueva reservación": todas las horas de un día (el primero que se puede de la semana) y hasta 2 horas. */
    public function create(): void
    {
        $this->authorize('bookOwn', Booking::class);
        $this->date = $this->bookableDays()[0] ?? '';
        $this->slotId = null;
        $this->choices = [];
        $this->choosing = true;
        unset($this->options);
    }

    /** Otro día en la ventana de "Nueva reservación" (solo días de la semana desde mañana). */
    public function updatedDate(): void
    {
        if (! in_array($this->date, $this->bookableDays(), true)) {
            $this->date = $this->bookableDays()[0] ?? '';
        }
        $this->choices = [];
        unset($this->options);
    }

    /** Días de la semana mostrada que el alumno puede agendar (desde mañana, sin festivos). @return list<string> */
    private function bookableDays(): array
    {
        $planner = app(AvailabilityPlanner::class);
        $school = $this->school();
        $days = $planner->days($school, CarbonImmutable::parse($this->week));
        $holidays = $planner->holidays($school, $days);

        return collect($days)->filter(fn (CarbonImmutable $day) => $day->gt($this->today()) && ! isset($holidays[$day->toDateString()]))
            ->map(fn (CarbonImmutable $day) => $day->toDateString())->values()->all();
    }

    public function book(AgendaService $agenda): void
    {
        $this->authorize('bookOwn', Booking::class);
        $student = $this->student ?? abort(403);
        if ($this->date === '') {
            $this->dispatch('toast', type: 'error', message: __('agenda.validation.option'));

            return;
        }

        $booked = $this->bookChoices($agenda, $this->school(), $student, CarbonImmutable::parse($this->date), byReception: false, clubsOnly: $this->clubsOnly);

        if ($booked === null) {
            return;
        }

        $this->choosing = null;
        $this->choices = [];
        unset($this->options, $this->bookings);
        $this->bookedToast($booked);
    }

    /** Abre la ventana para cancelar con un motivo del menú. */
    public function confirmCancel(int $bookingId): void
    {
        $this->authorize('cancelOwn', Booking::query()->findOrFail($bookingId));
        $this->resetValidation();
        $this->cancellingId = $bookingId;
        $this->cancelReasonId = null;
    }

    /** El alumno cancela con uno de "sus" motivos del catálogo (Lo puede elegir el alumno; no cuentan para la asistencia). */
    public function cancel(AgendaService $agenda): void
    {
        $booking = Booking::query()->findOrFail($this->cancellingId);
        $this->authorize('cancelOwn', $booking);

        $this->validate(['cancelReasonId' => ['required', 'integer', Rule::in($this->studentReasons()->modelKeys())]], [],
            ['cancelReasonId' => Str::lower(__('agenda.form.reason'))]);

        $agenda->cancel($booking, $this->studentReasons()->find($this->cancelReasonId), auth()->user());

        $this->cancellingId = null;
        unset($this->options, $this->bookings);
        $this->dispatch('toast', type: 'success', message: __('agenda.messages.cancelled'));
    }

    /** Motivos de cancelación del alumno (activos y marcados "Lo puede elegir el alumno"). @return EloquentCollection<int, CancellationReason> */
    private function studentReasons(): EloquentCollection
    {
        return CancellationReason::query()->where('is_active', true)->where('for_students', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function student(): ?Student
    {
        return Student::query()->where('user_id', auth()->id())->first();
    }

    /** Opciones de la hora elegida. @return array<int, array<string, mixed>> */
    #[Computed]
    public function options(): array
    {
        if (! $this->student || $this->date === '') {
            return [];
        }

        // Clic en una celda: solo esa hora; con "Nueva reservación", todas las del día.
        return collect(app(AgendaService::class)->options($this->school(), $this->student, CarbonImmutable::parse($this->date)))
            ->filter(fn (array $hour) => $this->slotId === null || $hour['slot']->id === $this->slotId)
            ->map(fn (array $hour) => [...$hour, 'options' => array_values(array_filter($hour['options'],
                fn (array $item) => ! $this->clubsOnly || $item['kind'] === BookingKind::Club->value))])
            ->filter(fn (array $hour) => $hour['options'] !== [])->values()->all();
    }

    /** Próximas reservaciones del alumno (de hoy en adelante, sin canceladas). @return Collection<int, Booking> */
    #[Computed]
    public function bookings(): Collection
    {
        if (! $this->student) {
            return collect();
        }

        return Booking::query()->where('student_id', $this->student->id)->active()
            ->where('date', '>=', $this->today()->toDateString())
            ->with(['slot', 'classroom', 'teacher', 'book', 'lesson', 'clubSession'])
            ->get()->sortBy(fn (Booking $booking) => $booking->date->toDateString().' '.$booking->slot?->starts_at)->values();
    }

    public function render(AgendaService $agenda, AvailabilityPlanner $planner): View
    {
        $school = $this->school();
        $days = $planner->days($school, CarbonImmutable::parse($this->week));
        $isReady = $planner->isGenerated($school, $days[0]);
        $availability = $isReady ? $agenda->weekAvailability($school, $days) : [];

        if ($this->clubsOnly) {
            $availability = array_map(fn (array $cell) => [...$cell,
                'session' => ['free' => 0, 'capacity' => 0], 'online' => ['free' => 0, 'capacity' => 0]], $availability);
        }

        // El alumno agenda desde el día siguiente: hoy y antes, como horas pasadas.
        $tomorrow = $this->today()->addDay()->toDateString();
        foreach ($availability as $at => $cell) {
            if (explode('|', $at)[0] < $tomorrow) {
                $availability[$at]['past'] = true;
            }
        }

        $current = $this->today()->startOfWeek(CarbonImmutable::MONDAY);

        return view('livewire.agenda.my-agenda', [
            'bookableDays' => collect($this->bookableDays())->mapWithKeys(fn (string $day) => [$day => Str::ucfirst(CarbonImmutable::parse($day)->translatedFormat('l j \d\e F'))])->all(),
            'studentReasons' => $this->studentReasons()->pluck('name', 'id')->all(),
            'slotLabel' => $this->slotId ? ScheduleSlot::query()->find($this->slotId)?->range() : null,
            'days' => $days,
            'planner' => $planner,
            'scheduleSlots' => $planner->slots($school),
            'holidays' => $planner->holidays($school, $days),
            'availability' => $availability,
            'isReady' => $isReady,
            'canGoBack' => $days[0]->gt($current),
            'canGoForward' => $days[0]->lt($current->addWeeks(self::WEEKS_AHEAD)),
            'nextActivity' => $this->student ? $agenda->nextActivity($this->student) : null,
        ])->title(__($this->clubsOnly ? 'agenda.my_clubs_title' : 'agenda.my_title'));
    }

    private function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->school()->timezone ?: config('appingles.timezone'))->startOfDay();
    }
}
