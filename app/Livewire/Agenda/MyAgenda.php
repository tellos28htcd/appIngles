<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\AgendaService;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\BookingKind;
use App\Enums\CancellationReason;
use App\Enums\ClubModality;
use App\Models\Booking;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Mi agenda (alumno): calendario semanal con los lugares libres por día y hora (asesoría y
 * clubs); clic en una celda para elegir y reservar; el sistema le asigna el aula (o la lista
 * de espera). Puede cancelar sus reservaciones. Desde "Agendar club" solo se reservan clubs.
 */
#[Layout('layouts.app')]
class MyAgenda extends Component
{
    /** Semanas hacia adelante que puede consultar. */
    public const WEEKS_AHEAD = 2;

    #[Locked]
    public bool $clubsOnly = false;

    /** Lunes de la semana que se muestra. */
    public string $week = '';

    public string $date = '';

    public ?int $slotId = null;

    public ?bool $choosing = null;

    public string $option = '';

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

    /** Clic en una celda del calendario: opciones de esa hora. */
    public function openSlot(string $date, int $slotId): void
    {
        $this->authorize('bookOwn', Booking::class);
        $day = CarbonImmutable::parse($date);
        abort_unless($day->startOfWeek(CarbonImmutable::MONDAY)->toDateString() === $this->week, 404);

        $this->date = $day->toDateString();
        $this->slotId = ScheduleSlot::query()->findOrFail($slotId)->id;
        $this->option = '';
        $this->choosing = true;
        unset($this->options);
    }

    public function book(AgendaService $agenda): void
    {
        $this->authorize('bookOwn', Booking::class);
        $student = $this->student ?? abort(403);
        [$slotId, $kind, $modality, $clubId] = array_pad(explode('|', $this->option), 4, '');
        $slot = ScheduleSlot::query()->find((int) $slotId);
        $kind = BookingKind::tryFrom($kind);
        $modality = ClubModality::tryFrom($modality);

        if (! $slot || ! $kind || ! $modality || $this->date === '' || ($this->clubsOnly && $kind !== BookingKind::Club)) {
            $this->dispatch('toast', type: 'error', message: __('agenda.validation.option'));

            return;
        }

        try {
            $booking = $agenda->book($this->school(), $student, CarbonImmutable::parse($this->date), $slot, $kind, $modality,
                $clubId === '' ? null : (int) $clubId, auth()->user(), byReception: false);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());

            return;
        }

        $this->choosing = null;
        $this->option = '';
        unset($this->options, $this->bookings);
        $this->dispatch('toast', type: $booking->is_waitlist ? 'warning' : 'success', message: $booking->is_waitlist
            ? __('agenda.messages.waitlisted')
            : __('agenda.messages.booked', ['place' => $booking->placeLabel()]));
    }

    /** El alumno cancela con "Aviso para cancelar". */
    public function cancel(int $bookingId, AgendaService $agenda): void
    {
        $booking = Booking::query()->findOrFail($bookingId);
        $this->authorize('cancelOwn', $booking);

        $agenda->cancel($booking, CancellationReason::Notice, auth()->user());

        unset($this->options, $this->bookings);
        $this->dispatch('toast', type: 'success', message: __('agenda.messages.cancelled'));
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
        if (! $this->student || $this->date === '' || $this->slotId === null) {
            return [];
        }

        return collect(app(AgendaService::class)->options($this->school(), $this->student, CarbonImmutable::parse($this->date)))
            ->filter(fn (array $hour) => $hour['slot']->id === $this->slotId)
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

        $current = $this->today()->startOfWeek(CarbonImmutable::MONDAY);

        return view('livewire.agenda.my-agenda', [
            'days' => $days,
            'planner' => $planner,
            'scheduleSlots' => $planner->slots($school),
            'holidays' => $planner->holidays($school, $days),
            'availability' => $availability,
            'isReady' => $isReady,
            'canGoBack' => $days[0]->gt($current),
            'canGoForward' => $days[0]->lt($current->addWeeks(self::WEEKS_AHEAD)),
            'slotLabel' => $this->slotId ? ScheduleSlot::query()->find($this->slotId)?->range() : null,
            'nextActivity' => $this->student ? $agenda->nextActivity($this->student) : null,
            'dailyLimit' => $this->student ? $agenda->dailyLimit($this->student, $this->today()) : null,
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
