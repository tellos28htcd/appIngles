<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\AgendaService;
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
 * Mi agenda (alumno): elige un día de la semana siguiente, ve los lugares disponibles y
 * reserva; el sistema le asigna el aula (o la lista de espera). Puede cancelar sus reservaciones.
 * Desde "Agendar club" solo se muestran los clubs.
 */
#[Layout('layouts.app')]
class MyAgenda extends Component
{
    public const DAYS_AHEAD = 7;

    #[Locked]
    public bool $clubsOnly = false;

    public string $date = '';

    public string $option = '';

    public function mount(): void
    {
        $this->authorize('bookOwn', Booking::class);
        $this->clubsOnly = request()->routeIs('my-agenda.clubs');
        $this->date = $this->days()[0]->toDateString();
    }

    public function selectDay(string $date): void
    {
        $this->date = collect($this->days())->first(fn (CarbonImmutable $day) => $day->toDateString() === $date)?->toDateString() ?? $this->date;
        $this->option = '';
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

        if (! $slot || ! $kind || ! $modality || ($this->clubsOnly && $kind !== BookingKind::Club)) {
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

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function options(): array
    {
        if (! $this->student) {
            return [];
        }

        $options = app(AgendaService::class)->options($this->school(), $this->student, CarbonImmutable::parse($this->date));

        if ($this->clubsOnly) {
            $options = collect($options)
                ->map(fn (array $hour) => [...$hour, 'options' => array_values(array_filter($hour['options'], fn (array $item) => $item['kind'] === BookingKind::Club->value))])
                ->filter(fn (array $hour) => $hour['options'] !== [])->values()->all();
        }

        return $options;
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

    public function render(AgendaService $agenda): View
    {
        return view('livewire.agenda.my-agenda', [
            'days' => $this->days(),
            'nextActivity' => $this->student ? $agenda->nextActivity($this->student) : null,
            'dailyLimit' => $this->student ? $agenda->dailyLimit($this->student, $this->today()) : null,
        ])->title(__($this->clubsOnly ? 'agenda.my_clubs_title' : 'agenda.my_title'));
    }

    /** Hoy y los días siguientes. @return list<CarbonImmutable> */
    private function days(): array
    {
        return collect(range(0, self::DAYS_AHEAD - 1))->map(fn (int $offset) => $this->today()->addDays($offset))->all();
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
