<?php

namespace App\Livewire\Kardex;

use App\Actions\Kardex\KardexService;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\GradeType;
use App\Models\Booking;
use App\Models\KardexEntry;
use App\Models\Role;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Teacher;
use App\Models\UngradedReason;
use App\Policies\KardexEntryPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Kardex → Calificaciones: el listado del día como en la Agenda (todos los reservados a cada hora; el teacher solo
 * los suyos). Clic en el alumno: ventana con la práctica que indica el Kardex y las 2 siguientes para calificar en
 * orden (cada una con su observación opcional del catálogo Motivos del Kardex: el teacher ve los suyos y el personal
 * los del personal), o "Sin calificación" con motivo. Se califica hasta 4 días después (con motivo y comentario); el
 * Coordinador académico y el Administrador corrigen o quitan calificaciones.
 */
#[Layout('layouts.app')]
class GradesDay extends Component
{
    #[Session]
    public string $date = '';

    #[Session]
    public string $filterSlot = '';

    #[Session]
    public string $filterTeacher = '';

    /** Dentro de la Agenda: solo la ventana de calificar (se abre con el botón "Calificaciones" de cada reservación). */
    #[Locked]
    public bool $embedded = false;

    /** Reservación abierta en la ventana de calificar. */
    public ?int $gradingId = null;

    /** Calificación elegida para cada práctica mostrada (en orden). @var list<string> */
    public array $grades = ['', '', ''];

    /** Observación (motivo del Kardex, opcional) de cada práctica mostrada. @var list<string> */
    public array $observations = ['', '', ''];

    public ?int $lateReasonId = null;

    public string $lateComment = '';

    /** "Sin calificación" con motivo y comentario. */
    public bool $ungraded = false;

    public ?int $ungradedReasonId = null;

    public string $ungradedComment = '';

    /** Corrección (Coordinador / Administrador): kardex_entry => grade. @var array<int, string> */
    public array $corrections = [];

    public function mount(): void
    {
        $this->authorize('grade', KardexEntry::class);

        if (! $this->validDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }
    }

    public function updatedDate(): void
    {
        if (! $this->validDate($this->date)) {
            $this->date = $this->today()->toDateString();
        }
        unset($this->bookings);
    }

    public function shiftDay(int $days): void
    {
        $next = CarbonImmutable::parse($this->date)->addDays(max(-1, min(1, $days)))->toDateString();
        if ($this->validDate($next)) {
            $this->date = $next;
        }
        unset($this->bookings);
    }

    public function goToday(): void
    {
        $this->date = $this->today()->toDateString();
        unset($this->bookings);
    }

    public function clearFilters(): void
    {
        $this->reset('filterSlot', 'filterTeacher');
        unset($this->bookings);
    }

    // ── Ventana de calificar ──────────────────────────────────────

    /** Botón "Calificaciones" de una reservación en la Agenda. */
    #[On('kardex-grade')]
    public function openFromAgenda(int $bookingId): void
    {
        $this->open($bookingId);
    }

    public function open(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);
        $this->authorize('gradeBooking', [KardexEntry::class, $booking]);

        $this->resetValidation();
        $this->reset('grades', 'observations', 'lateReasonId', 'lateComment', 'ungraded', 'ungradedReasonId', 'ungradedComment');
        $this->ungradedReasonId = $booking->ungraded_reason_id;
        $this->ungradedComment = (string) $booking->ungraded_comment;
        $this->corrections = $booking->kardexEntries()->withoutGlobalScope('school')->pluck('grade_id', 'id')->map(fn ($id) => (string) $id)->all();
        $this->gradingId = $booking->id;
    }

    public function save(KardexService $kardex): void
    {
        $booking = $this->findBooking($this->gradingId);
        $this->authorize('gradeBooking', [KardexEntry::class, $booking]);
        $this->resetValidation();

        if (! $booking->kind->isPractice()) {
            $kardex->gradeClub($booking, $this->grades[0] !== '' ? (int) $this->grades[0] : null, auth()->user(), $this->today(), $this->lateReasonId, $this->lateComment, $this->observations[0] ?? null);
            $saved = 1;
        } else {
            $saved = $kardex->gradeSession($booking, $this->grades, auth()->user(), $this->today(), $this->lateReasonId, $this->lateComment, $this->observations);
        }

        $this->gradingId = null;
        unset($this->bookings);
        $this->dispatch('kardex-saved');
        $this->dispatch('toast', type: 'success', message: trans_choice('kardex.messages.graded', $saved));
    }

    public function saveUngraded(KardexService $kardex): void
    {
        $booking = $this->findBooking($this->gradingId);
        $this->authorize('gradeBooking', [KardexEntry::class, $booking]);
        $this->resetValidation();

        $kardex->markUngraded($booking, $this->ungradedReasonId, $this->ungradedComment, $this->today(), auth()->user());

        $this->gradingId = null;
        unset($this->bookings);
        $this->dispatch('kardex-saved');
        $this->dispatch('toast', type: 'success', message: __('kardex.messages.ungraded'));
    }

    public function changeGrade(int $entryId, KardexService $kardex): void
    {
        $this->authorize('correct', KardexEntry::class);
        $entry = $this->findEntry($entryId);
        $this->resetValidation();

        $kardex->changeGrade($entry, (int) ($this->corrections[$entryId] ?? 0));

        unset($this->bookings);
        $this->dispatch('toast', type: 'success', message: __('kardex.messages.corrected'));
    }

    public function removeEntry(int $entryId, KardexService $kardex): void
    {
        $this->authorize('correct', KardexEntry::class);
        $entry = $this->findEntry($entryId);
        $this->resetValidation();

        $kardex->remove($entry);

        unset($this->corrections[$entryId], $this->bookings);
        $this->dispatch('toast', type: 'success', message: __('kardex.messages.removed'));
    }

    // ── Datos ─────────────────────────────────────────────────────

    /**
     * Reservaciones del día (sin canceladas ni lista de espera) agrupadas por hora; el teacher solo ve las suyas.
     *
     * @return Collection<int, array{slot: ScheduleSlot, bookings: Collection<int, Booking>}>
     */
    #[Computed]
    public function bookings(): Collection
    {
        return $this->dayBookings()
            ->when($this->filterSlot !== '', fn (Collection $items) => $items->where('schedule_slot_id', (int) $this->filterSlot))
            ->when($this->filterTeacher !== '', fn (Collection $items) => $items->where('teacher_id', (int) $this->filterTeacher))
            ->groupBy('schedule_slot_id')
            ->map(fn (Collection $items) => [
                'slot' => $items->first()->slot,
                'bookings' => $items->sortBy(fn (Booking $booking) => sprintf('%s|%s|%s', $booking->placeLabel(), $booking->teacher?->panelName() ?? 'zz', $booking->student?->fullName()))->values(),
            ])
            ->sortBy(fn (array $group) => $group['slot']?->starts_at)
            ->values();
    }

    /** @return Collection<int, Booking> */
    private function dayBookings(): Collection
    {
        $teacherId = $this->isTeacher() ? (app(KardexEntryPolicy::class)->teacherIdOf(auth()->user()) ?? 0) : null;

        return Booking::query()->whereDate('date', $this->date)->active()->where('is_waitlist', false)->whereIn('kind', BookingKind::gradableKinds())
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))
            ->with(['student', 'slot', 'teacher', 'classroom', 'book', 'lesson', 'clubSession', 'ungradedReason', 'kardexEntries.grade', 'kardexEntries.book', 'kardexEntries.lesson'])
            ->get();
    }

    /**
     * Lo de la ventana abierta: reservación, prácticas por calificar con sus calificaciones posibles y lo ya calificado.
     *
     * @return array{booking: ?Booking, steps: list<array<string, mixed>>, options: array<int, array<int, string>>, byType: array<string, array<int, string>>, late: bool, gradable: bool, attended: bool}
     */
    private function gradingData(KardexService $kardex): array
    {
        $booking = $this->gradingId ? Booking::query()->with(['student', 'slot', 'teacher', 'clubSession', 'ungradedReason', 'kardexEntries.grade', 'kardexEntries.book', 'kardexEntries.lesson', 'kardexEntries.user', 'kardexEntries.observationReason'])->find($this->gradingId) : null;

        if ($booking === null) {
            return ['booking' => null, 'steps' => [], 'options' => [], 'byType' => [], 'late' => false, 'gradable' => false, 'attended' => false];
        }

        // Club y presentaciones: una sola firma (F); la práctica, las prácticas que siguen en el Kardex.
        $isClub = ! $booking->kind->isPractice();
        $steps = $isClub
            ? ($booking->kardexEntries->isEmpty() ? [['label' => $booking->activityLabel(), 'type' => GradeType::Signature]] : [])
            : $kardex->pendingSteps($booking);
        $byType = collect(GradeType::cases())->mapWithKeys(fn (GradeType $type) => [$type->value => $kardex->grades($booking->school_id, $type)
            ->mapWithKeys(fn ($grade) => [$grade->id => $grade->code])->all()]);

        return [
            'booking' => $booking,
            'steps' => $steps,
            'options' => collect($steps)->map(fn (array $step) => $byType[$step['type']->value])->all(),
            'byType' => $byType->all(),
            'late' => $kardex->isLate($booking, $this->today()),
            'gradable' => $booking->date->toDateString() >= $kardex->earliestDate($this->today())->toDateString(),
            'attended' => $booking->status === BookingStatus::Attended,
        ];
    }

    public function render(KardexService $kardex): View
    {
        $day = $this->embedded ? collect() : $this->dayBookings();

        return view('livewire.kardex.grades-day', [
            'day' => CarbonImmutable::parse($this->date),
            'grading' => $this->gradingData($kardex),
            'reasonOptions' => UngradedReason::query()->usableBy(auth()->user())->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'filterSlots' => $day->pluck('slot')->filter()->unique('id')->sortBy('starts_at')->mapWithKeys(fn (ScheduleSlot $slot) => [$slot->id => $slot->range()])->all(),
            'filterTeachers' => $this->isTeacher() ? [] : $day->pluck('teacher')->filter()->unique('id')->sortBy(fn (Teacher $teacher) => $teacher->panelName())
                ->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->panelName()])->all(),
            'canCorrect' => auth()->user()->can('correct', KardexEntry::class),
            'earliest' => $kardex->earliestDate($this->today()),
            'today' => $this->today(),
            'daysBack' => KardexService::DAYS_BACK,
        ])->title(__('kardex.grades_title'));
    }

    // ── Apoyo ─────────────────────────────────────────────────────

    private function isTeacher(): bool
    {
        return auth()->user()->loadMissing('role')->role?->slug === Role::TEACHER;
    }

    private function findBooking(?int $bookingId): Booking
    {
        return Booking::query()->findOrFail($bookingId);
    }

    private function findEntry(int $entryId): KardexEntry
    {
        return KardexEntry::query()->where('booking_id', $this->gradingId)->findOrFail($entryId);
    }

    private function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->school()->timezone ?: config('appingles.timezone'))->startOfDay();
    }

    /**
     * Fecha válida: no futura y, para calificar, hasta 4 días atrás; el Coordinador y el Administrador pueden ver
     * días anteriores para corregir.
     */
    private function validDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || strtotime($value) === false || $value > $this->today()->toDateString()) {
            return false;
        }

        return auth()->user()->can('correct', KardexEntry::class)
            || $value >= app(KardexService::class)->earliestDate($this->today())->toDateString();
    }
}
