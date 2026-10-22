<?php

namespace App\Livewire\Teachers;

use App\Actions\Clubs\ScheduleClub;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\ClubModality;
use App\Models\ClassroomClosure;
use App\Models\Club;
use App\Models\ClubSession;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Teachers → Programación de clubs: calendario del mes (una cuadrícula por semana,
 * días × horas). En cada hora se agrega el club (abreviatura), el teacher y si es
 * presencial o en línea; se cambia o se elimina desde la misma celda. Las reglas
 * las aplica ScheduleClub.
 */
#[Layout('layouts.app')]
class ClubSchedule extends Component
{
    #[Url(as: 'mes', except: '')]
    public string $month = '';

    /** Club que se edita (null = nuevo). */
    public ?int $sessionId = null;

    public ?bool $editing = null;

    public string $date = '';

    public ?int $slotId = null;

    /** "club_id|CODE". */
    public string $codeKey = '';

    public ?int $teacherId = null;

    public string $modality = 'in_person';

    public ?int $classroomId = null;

    /** Repetir el mismo día y hora en las semanas siguientes del mes. */
    public bool $repeat = false;

    public function mount(): void
    {
        if (auth()->user()->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('availability.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $this->authorize('viewAny', ClubSession::class);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now(config('appingles.timezone'))->format('Y-m');
        }
    }

    private function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function add(string $date, int $slotId): void
    {
        $this->authorize('manage', ClubSession::class);
        $this->resetValidation();
        $this->reset('sessionId', 'codeKey', 'teacherId', 'classroomId', 'repeat');
        $this->modality = ClubModality::InPerson->value;
        $this->date = CarbonImmutable::parse($date)->toDateString();
        $this->slotId = $slotId;
        $this->editing = true;
    }

    public function edit(int $sessionId): void
    {
        $this->authorize('manage', ClubSession::class);
        $this->resetValidation();
        $session = $this->find($sessionId);

        $this->sessionId = $session->id;
        $this->date = $session->date->toDateString();
        $this->slotId = $session->schedule_slot_id;
        $this->codeKey = $session->club_id.'|'.$session->code;
        $this->teacherId = $session->teacher_id;
        $this->modality = $session->modality->value;
        $this->classroomId = $session->classroom_id;
        $this->repeat = false;
        $this->editing = true;
    }

    public function save(ScheduleClub $scheduler): void
    {
        $this->authorize('manage', ClubSession::class);
        $this->validate([
            'codeKey' => ['required', 'string'],
            'teacherId' => ['required', 'integer'],
            'modality' => ['required', 'in:'.implode(',', array_column(ClubModality::cases(), 'value'))],
            'date' => ['required', 'date'],
            'slotId' => ['required', 'integer'],
        ], attributes: [
            'codeKey' => mb_strtolower(__('clubs.fields.code')),
            'teacherId' => mb_strtolower(__('clubs.fields.teacher')),
            'modality' => mb_strtolower(__('clubs.fields.modality')),
        ]);

        $school = $this->school();
        [$clubId, $code] = array_pad(explode('|', $this->codeKey, 2), 2, '');
        $data = [
            'club_id' => (int) $clubId, 'code' => $code, 'teacher_id' => $this->teacherId, 'date' => $this->date,
            'schedule_slot_id' => $this->slotId, 'modality' => $this->modality, 'classroom_id' => $this->classroomId,
        ];

        $scheduler->save($school, $data, $this->sessionId ? $this->find($this->sessionId) : null, auth()->id());

        // Repetir: mismo día de la semana y hora en las semanas siguientes del mes (las que no se puedan se avisan).
        $skipped = [];
        if ($this->repeat && $this->sessionId === null) {
            $start = CarbonImmutable::parse($this->date);
            for ($day = $start->addWeek(); $day->month === $start->month; $day = $day->addWeek()) {
                try {
                    $scheduler->save($school, [...$data, 'date' => $day->toDateString(), 'classroom_id' => null], null, auth()->id());
                } catch (ValidationException $exception) {
                    $skipped[] = $day->translatedFormat('j M').': '.collect($exception->errors())->flatten()->first();
                }
            }
        }

        $this->editing = null;
        unset($this->sessions);
        $this->dispatch('toast', ...($skipped === []
            ? ['type' => 'success', 'message' => __('clubs.messages.saved')]
            : ['type' => 'warning', 'message' => __('clubs.messages.saved_with_skips', ['days' => implode(' · ', $skipped)])]));
    }

    public function delete(ScheduleClub $scheduler): void
    {
        $this->authorize('manage', ClubSession::class);
        $scheduler->delete($this->find((int) $this->sessionId));

        $this->editing = null;
        unset($this->sessions);
        $this->dispatch('toast', type: 'success', message: __('clubs.messages.deleted'));
    }

    private function find(int $sessionId): ClubSession
    {
        return ClubSession::query()->where('school_id', auth()->user()->school_id)->findOrFail($sessionId);
    }

    /** Clubs del mes. @return \Illuminate\Database\Eloquent\Collection<int, ClubSession> */
    #[Computed]
    public function sessions(): Collection
    {
        $first = CarbonImmutable::parse($this->month.'-01');

        return ClubSession::query()->where('school_id', auth()->user()->school_id)
            ->whereBetween('date', [$first->toDateString(), $first->endOfMonth()->toDateString()])
            ->with(['club', 'teacher', 'classroom'])->get();
    }

    /**
     * Abreviaturas activas de los clubes de la escuela.
     *
     * @return array<string, array{code: string, label: string, color: int}> "club_id|CODE" => datos
     */
    #[Computed]
    public function codeCatalog(): array
    {
        return Club::query()->where('school_id', auth()->user()->school_id)->where('is_active', true)->orderBy('sort_order')->get()
            ->flatMap(fn (Club $club) => collect($club->codeList())->filter(fn (array $code) => $code['is_active'] ?? true)
                ->mapWithKeys(fn (array $code) => [$club->id.'|'.$code['code'] => [
                    'code' => $code['code'],
                    'label' => $code['code'].' · '.$club->name.' ('.collect($code['levels'])->map(fn ($level) => 'N'.$level)->implode(', ').')',
                    'color' => (int) ($code['color'] ?? 1),
                ]]))
            ->all();
    }

    /** @return array<string, string> "club_id|CODE" => "C12 · Conversation Club (N1, N2)" */
    #[Computed]
    public function codeOptions(): array
    {
        return array_map(fn (array $code) => $code['label'], $this->codeCatalog);
    }

    /** Teachers que pueden dar el club a esa hora (más el que ya lo tiene). @return array<int, string> */
    #[Computed]
    public function teacherOptions(): array
    {
        if (! $this->editing || $this->date === '' || $this->slotId === null) {
            return [];
        }

        $planner = app(AvailabilityPlanner::class);
        $school = $this->school();
        $slot = $planner->slots($school)->firstWhere('id', $this->slotId);

        if ($slot === null) {
            return [];
        }

        $ids = $planner->availableTeacherIds($school, CarbonImmutable::parse($this->date), $slot, $this->sessionId);

        return Teacher::query()->where('school_id', $school->id)->whereKey($ids)->where('is_waitlist', false)
            ->orderBy('panel_number')->get()->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->panelName()])->all();
    }

    /** Aulas audiovisuales libres para la hora elegida. @return array<int, string> */
    #[Computed]
    public function roomOptions(): array
    {
        if (! $this->editing || $this->date === '' || $this->slotId === null) {
            return [];
        }

        $school = $this->school();
        $slot = app(AvailabilityPlanner::class)->slots($school)->firstWhere('id', $this->slotId);

        return $slot === null ? [] : app(ScheduleClub::class)->freeRooms($school, CarbonImmutable::parse($this->date), $slot, $this->sessionId)
            ->mapWithKeys(fn ($room) => [$room->id => __('clubs.room', ['name' => $room->name])])->all();
    }

    public function render(AvailabilityPlanner $planner, ScheduleClub $scheduler): View
    {
        $school = $this->school();
        $first = CarbonImmutable::parse($this->month.'-01');
        $slots = $planner->slots($school);
        $rooms = $scheduler->clubRooms($school);

        // Semanas del mes (de lunes a sábado/domingo); los días de otro mes se muestran vacíos.
        $weeks = [];
        for ($monday = $first->startOfWeek(CarbonInterface::MONDAY); $monday->lte($first->endOfMonth()); $monday = $monday->addWeek()) {
            $weeks[] = $planner->days($school, $monday);
        }
        $allDays = collect($weeks)->flatten(1);
        $holidays = $planner->holidays($school, $allDays->all());

        $closures = ClassroomClosure::query()->where('school_id', $school->id)->whereIn('classroom_id', $rooms->modelKeys())
            ->whereDate('starts_on', '<=', $first->endOfMonth()->toDateString())->whereDate('ends_on', '>=', $first->toDateString())
            ->with(['fromSlot', 'toSlot'])->get();

        // Cupo por hora: aulas audiovisuales sin cierre menos los clubs ya programados.
        $capacity = fn (CarbonImmutable $day, $slot) => $rooms->reject(fn ($room) => $closures->contains(
            fn (ClassroomClosure $closure) => $closure->classroom_id === $room->id && $closure->covers($day, $slot)))->count();

        // Clubs cuyo teacher ya no tiene esa hora en el panel armado (p. ej. por una falta sin cubrir).
        $linked = TeacherAvailability::query()->where('school_id', $school->id)->whereIn('club_session_id', $this->sessions->modelKeys())->pluck('club_session_id');
        $armed = collect($weeks)->mapWithKeys(fn (array $days) => [$days[0]->toDateString() => $planner->isGenerated($school, $days[0])]);
        $orphans = $this->sessions->filter(fn (ClubSession $session) => ($armed[$session->date->startOfWeek(CarbonInterface::MONDAY)->toDateString()] ?? false)
            && ! $linked->contains($session->id))->modelKeys();

        return view('livewire.teachers.club-schedule', [
            'school' => $school,
            'monthStart' => $first,
            'weeks' => $weeks,
            'scheduleSlots' => $slots,
            'holidays' => $holidays,
            'grid' => $this->sessions->groupBy(fn (ClubSession $session) => $session->date->toDateString().'|'.$session->schedule_slot_id),
            'capacity' => $capacity,
            'rooms' => $rooms,
            'orphans' => $orphans,
            'planner' => $planner,
            'canManage' => auth()->user()->can('manage', ClubSession::class),
            'modalities' => collect(ClubModality::cases())->mapWithKeys(fn (ClubModality $modality) => [$modality->value => $modality->label()])->all(),
            'slotLabel' => $this->slotId ? $slots->firstWhere('id', $this->slotId)?->range() : null,
        ])->title(__('clubs.title'));
    }
}
