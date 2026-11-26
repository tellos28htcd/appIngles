<?php

namespace App\Livewire\Agenda\Concerns;

use App\Actions\Agenda\AgendaService;
use App\Enums\BookingKind;
use App\Enums\ClubModality;
use App\Models\Booking;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nueva reservación (recepción y alumno): se eligen hasta MAX_CHOICES opciones (o el caso especial del alumno), cada una a una hora distinta
 * ("slot|kind|modality|club_session_id"), y se reservan juntas (si una no se puede, no se reserva ninguna).
 */
trait ChoosesBookingOptions
{
    /** Opciones elegidas, una por hora. @var list<string> */
    public array $choices = [];

    /** Modalidad que se muestra (primero se elige Presencial o En línea y luego el tipo). */
    public string $modality = 'in_person';

    public function updatedModality(): void
    {
        $this->modality = in_array($this->modality, ['in_person', 'online'], true) ? $this->modality : 'in_person';
    }

    /** Grupo que se muestra después de la modalidad: "Reservación" (práctica y club) o "Especial" (mentoría, material y presentación). */
    public string $bookingGroup = 'regular';

    public function updatedBookingGroup(): void
    {
        $this->bookingGroup = array_key_exists($this->bookingGroup, BookingKind::GROUPS) ? $this->bookingGroup : 'regular';
    }

    public const MAX_CHOICES = 2;

    /** Alumno de la reservación (para su caso especial). */
    abstract protected function bookingStudent(): ?Student;

    /** Horas que se eligen a la vez: MAX_CHOICES, o su caso especial si es mayor. */
    public function maxChoices(): int
    {
        return max(self::MAX_CHOICES, (int) $this->bookingStudent()?->max_daily_practices);
    }

    /** Elige o quita una opción; otra opción de la misma hora la reemplaza. */
    public function toggleChoice(string $value): void
    {
        if (in_array($value, $this->choices, true)) {
            $this->choices = array_values(array_diff($this->choices, [$value]));

            return;
        }

        $slotId = strtok($value, '|');
        $others = array_values(array_filter($this->choices, fn (string $choice) => strtok($choice, '|') !== $slotId));

        if (count($others) >= $this->maxChoices()) {
            $this->dispatch('toast', type: 'error', message: __('agenda.validation.max_choices', ['max' => $this->maxChoices()]));

            return;
        }

        $this->choices = [...$others, $value];
    }

    /**
     * Reserva todas las opciones elegidas (en orden de hora) en una sola operación.
     *
     * @return list<Booking>|null null si hubo error (ya se avisó)
     */
    protected function bookChoices(AgendaService $agenda, School $school, Student $student, CarbonImmutable $date, bool $byReception, bool $clubsOnly = false, bool $duringClass = false): ?array
    {
        $parsed = [];
        foreach (array_slice($this->choices, 0, max(self::MAX_CHOICES, (int) $student->max_daily_practices)) as $choice) {
            [$slotId, $kind, $modality, $clubId] = array_pad(explode('|', $choice), 4, '');
            $slot = ScheduleSlot::query()->find((int) $slotId);
            $kind = BookingKind::tryFrom($kind);
            $modality = ClubModality::tryFrom($modality);

            if (! $slot || ! $kind || ! $modality || ($clubsOnly && $kind !== BookingKind::Club)) {
                $parsed = [];
                break;
            }
            $parsed[$slot->id] = [$slot, $kind, $modality, $clubId === '' ? null : (int) $clubId];
        }

        if ($parsed === []) {
            $this->dispatch('toast', type: 'error', message: __('agenda.validation.option'));

            return null;
        }

        uasort($parsed, fn (array $a, array $b) => strcmp((string) $a[0]->starts_at, (string) $b[0]->starts_at));

        try {
            return DB::transaction(fn () => array_values(array_map(
                fn (array $item) => $agenda->book($school, $student, $date, $item[0], $item[1], $item[2], $item[3], auth()->user(), byReception: $byReception, duringClass: $duringClass)
                    ->setRelation('slot', $item[0]),
                $parsed,
            )));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());

            return null;
        }
    }

    /** Aviso de lo reservado. @param list<Booking> $bookings */
    protected function bookedToast(array $bookings): void
    {
        $waitlisted = collect($bookings)->contains('is_waitlist', true);
        $message = collect($bookings)->map(fn (Booking $booking) => $booking->is_waitlist
            ? substr((string) $booking->slot?->starts_at, 0, 5).' '.__('agenda.messages.waitlisted')
            : __('agenda.messages.booked', ['place' => (count($bookings) > 1 ? substr((string) $booking->slot?->starts_at, 0, 5).' · ' : '').$booking->placeLabel()]))
            ->implode(' ');

        $this->dispatch('toast', type: $waitlisted ? 'warning' : 'success', message: $message);
    }
}
