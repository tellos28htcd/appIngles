<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\AgendaService;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\BookingKind;
use App\Enums\CancellationReason;
use App\Enums\ClubModality;
use App\Enums\StudentStatus;
use App\Models\Booking;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Agenda del día (recepción): por hora, las reservaciones ordenadas por aula → teacher →
 * alumno con su actividad o club. Aquí se agenda (eligiendo al alumno; el aula la asigna
 * el sistema), se cancela con motivo, se marca asistencia y se pasa a la lista de espera a un aula.
 */
#[Layout('layouts.app')]
class AgendaDay extends Component
{
    #[Url(as: 'fecha', except: '')]
    public string $date = '';

    public ?bool $creating = null;

    public string $search = '';

    public ?int $studentId = null;

    /** Opción elegida: "slot|kind|modality|club_session_id". */
    public string $option = '';

    public ?int $cancellingId = null;

    public string $reason = 'notice';

    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);

        if (! $this->validDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }
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

    public function goToday(): void
    {
        $this->date = $this->today()->toDateString();
        unset($this->bookings, $this->proposals);
    }

    // ── Nueva reservación ─────────────────────────────────────────

    public function create(): void
    {
        $this->authorize('manage', Booking::class);
        $this->reset('search', 'studentId', 'option');
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
        $this->reason = CancellationReason::Notice->value;
    }

    public function cancel(AgendaService $agenda): void
    {
        $this->authorize('manage', Booking::class);
        $this->validate(['reason' => ['required', Rule::enum(CancellationReason::class)]]);

        $agenda->cancel($this->findBooking($this->cancellingId), CancellationReason::from($this->reason), auth()->user());

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
            ->with(['student', 'slot', 'teacher', 'classroom', 'book', 'lesson', 'clubSession.club', 'events.user'])
            ->get();

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

        return $student ? app(AgendaService::class)->options($this->school(), $student, $this->day()) : [];
    }

    #[Computed]
    public function selectedStudent(): ?Student
    {
        return $this->studentId ? Student::query()->find($this->studentId) : null;
    }

    public function render(AgendaService $agenda): View
    {
        $school = $this->school();

        return view('livewire.agenda.agenda-day', [
            'canManage' => auth()->user()->can('manage', Booking::class),
            'day' => $this->day(),
            'isReady' => app(AvailabilityPlanner::class)->isGenerated($school, $this->day()->startOfWeek(CarbonImmutable::MONDAY)),
            'reasonOptions' => collect(CancellationReason::cases())->mapWithKeys(fn (CancellationReason $reason) => [$reason->value => $reason->label()])->all(),
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
