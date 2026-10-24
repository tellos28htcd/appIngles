<?php

namespace App\Livewire\Teachers;

use App\Actions\Clubs\ScheduleClub;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\ClubModality;
use App\Models\ClassroomClosure;
use App\Models\Club;
use App\Models\ClubSession;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Teachers → Programación de clubs: calendario del mes (una cuadrícula por semana,
 * días × horas). En cada hora se agrega el club (abreviatura), el teacher y si es
 * presencial o en línea; se cambia o se elimina desde la misma celda. Arriba, el
 * resumen de horas por teacher y por tipo de club (mes elegido y mes anterior).
 * Las reglas las aplica ScheduleClub.
 */
#[Layout('layouts.app')]
class ClubSchedule extends Component
{
    #[Url(as: 'mes', except: '')]
    public string $month = '';

    /** Pestaña del resumen abierta: teacher | type ('' = colapsado). */
    #[Url(as: 'resumen', except: '')]
    public string $summaryTab = '';

    /** Abre la pestaña del resumen; si ya estaba abierta, la colapsa. */
    public function toggleSummary(string $tab): void
    {
        $this->summaryTab = $this->summaryTab === $tab || ! in_array($tab, ['teacher', 'type'], true) ? '' : $tab;
    }

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

    /** Repetir el mismo club (día y hora) en las semanas siguientes del mes. */
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
        $this->repeat = $session->repeats;
        $this->editing = true;
    }

    /** Al cambiar la modalidad cambia el tipo de aula (física o virtual). */
    public function updatedModality(): void
    {
        $this->classroomId = null;
    }

    public function save(ScheduleClub $scheduler): void
    {
        $this->authorize('manage', ClubSession::class);

        // Sin aula libre de esa modalidad se dice el motivo (en vez de "elige un aula").
        if ($this->classroomId === null && $this->roomOptions === [] && ($modality = ClubModality::tryFrom($this->modality)) !== null) {
            throw ValidationException::withMessages(['classroomId' => __('clubs.validation.no_room_'.$modality->value)]);
        }

        $this->validate([
            'codeKey' => ['required', 'string'],
            'teacherId' => ['required', 'integer'],
            'modality' => ['required', 'in:'.implode(',', array_column(ClubModality::cases(), 'value'))],
            'date' => ['required', 'date'],
            'slotId' => ['required', 'integer'],
            'classroomId' => ['required', 'integer'],
        ], attributes: [
            'classroomId' => mb_strtolower(__($this->modality === ClubModality::Online->value ? 'clubs.fields.virtual_room' : 'clubs.fields.room')),
            'codeKey' => mb_strtolower(__('clubs.fields.code')),
            'teacherId' => mb_strtolower(__('clubs.fields.teacher')),
            'modality' => mb_strtolower(__('clubs.fields.modality')),
        ]);

        $school = $this->school();
        [$clubId, $code] = array_pad(explode('|', $this->codeKey, 2), 2, '');
        $data = [
            'club_id' => (int) $clubId, 'code' => $code, 'teacher_id' => $this->teacherId, 'date' => $this->date,
            'schedule_slot_id' => $this->slotId, 'modality' => $this->modality, 'classroom_id' => $this->classroomId,
            'repeats' => $this->repeat,
        ];

        $scheduler->save($school, $data, $this->sessionId ? $this->find($this->sessionId) : null, auth()->id());

        // Repetir (al agregar o al editar): mismo club, teacher y modalidad el mismo día y hora de las
        // semanas siguientes del mes. Las semanas donde ya está o no se puede se avisan.
        $skipped = [];
        if ($this->repeat) {
            $start = CarbonImmutable::parse($this->date);
            for ($day = $start->addWeek(); $day->month === $start->month; $day = $day->addWeek()) {
                $already = ClubSession::query()->where('school_id', $school->id)->whereDate('date', $day->toDateString())
                    ->where('schedule_slot_id', $this->slotId)->where('teacher_id', $this->teacherId)->where('code', $code)->exists();

                if ($already) {
                    continue;
                }

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

    /** Clubs de un mes ("AAAA-MM"; por omisión el elegido). @return Collection<int, ClubSession> */
    private function sessionsOf(string $month): Collection
    {
        $first = CarbonImmutable::parse($month.'-01');

        return ClubSession::query()->where('school_id', auth()->user()->school_id)
            ->whereBetween('date', [$first->toDateString(), $first->endOfMonth()->toDateString()])
            ->with(['club', 'teacher', 'classroom', 'slot'])->get();
    }

    /** Clubs del mes elegido. @return Collection<int, ClubSession> */
    #[Computed]
    public function sessions(): Collection
    {
        return $this->sessionsOf($this->month);
    }

    /** Horas de un club programado (duración de su bloque de horario). */
    private static function hours(ClubSession $session): float
    {
        $slot = $session->slot;

        return $slot === null ? 1.0 : max(0.0, (strtotime($slot->ends_at) - strtotime($slot->starts_at)) / 3600);
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
        $slot = $this->editingSlot();

        if ($slot === null) {
            return [];
        }

        $school = $this->school();
        $ids = app(AvailabilityPlanner::class)->availableTeacherIds($school, CarbonImmutable::parse($this->date), $slot, $this->sessionId);

        return Teacher::query()->where('school_id', $school->id)->whereKey($ids)->where('is_waitlist', false)
            ->orderBy('panel_number')->get()->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->panelName()])->all();
    }

    /** Aulas libres para la hora y modalidad elegidas (física o virtual). @return array<int, string> */
    #[Computed]
    public function roomOptions(): array
    {
        $slot = $this->editingSlot();
        $modality = ClubModality::tryFrom($this->modality);

        if ($slot === null || $modality === null) {
            return [];
        }

        return app(ScheduleClub::class)->freeRooms($this->school(), CarbonImmutable::parse($this->date), $slot, $modality, $this->sessionId)
            ->mapWithKeys(fn ($room) => [$room->id => __('clubs.room', ['name' => $room->name])])->all();
    }

    private function editingSlot(): ?ScheduleSlot
    {
        if (! $this->editing || $this->date === '' || $this->slotId === null) {
            return null;
        }

        return app(AvailabilityPlanner::class)->slots($this->school())->firstWhere('id', $this->slotId);
    }

    /**
     * Resumen de un mes: horas por teacher y por abreviatura.
     *
     * @return array{label: string, teachers: list<array{name: string, hours: float, codes: array<string, float>}>, codes: list<string>, total: float}
     */
    private function summary(string $month): array
    {
        $sessions = $month === $this->month ? $this->sessions : $this->sessionsOf($month);
        $codes = $sessions->pluck('code')->unique()->sort()->values()->all();

        $teachers = $sessions->groupBy('teacher_id')->map(fn (Collection $items) => [
            'name' => $items->first()->teacher?->panelName() ?? '—',
            'number' => $items->first()->teacher?->panel_number ?? 999,
            'hours' => $items->sum(fn (ClubSession $session) => self::hours($session)),
            'codes' => $items->groupBy('code')->map(fn (Collection $byCode) => $byCode->sum(fn (ClubSession $session) => self::hours($session)))->all(),
        ])->sortBy([['number', 'asc'], ['name', 'asc']])->values()->all();

        return [
            'label' => Str::ucfirst(CarbonImmutable::parse($month.'-01')->translatedFormat('F Y')),
            'teachers' => $teachers,
            'codes' => $codes,
            'total' => $sessions->sum(fn (ClubSession $session) => self::hours($session)),
        ];
    }

    public function render(AvailabilityPlanner $planner, ScheduleClub $scheduler): View
    {
        $school = $this->school();
        $first = CarbonImmutable::parse($this->month.'-01');
        $slots = $planner->slots($school);
        $physical = $scheduler->clubRooms($school, ClubModality::InPerson);
        $virtual = $scheduler->clubRooms($school, ClubModality::Online);

        // Semanas del mes (de lunes a sábado/domingo); los días de otro mes se muestran vacíos.
        $weeks = [];
        for ($monday = $first->startOfWeek(CarbonInterface::MONDAY); $monday->lte($first->endOfMonth()); $monday = $monday->addWeek()) {
            $weeks[] = $planner->days($school, $monday);
        }
        $holidays = $planner->holidays($school, collect($weeks)->flatten(1)->all());

        // Reservas de las aulas para clubs (otra actividad) que tocan el mes.
        $closures = ClassroomClosure::query()->where('school_id', $school->id)->whereIn('classroom_id', $physical->merge($virtual)->modelKeys())
            ->whereDate('starts_on', '<=', $first->endOfMonth()->toDateString())->whereDate('ends_on', '>=', $first->startOfWeek()->toDateString())
            ->with(['fromSlot', 'toSlot', 'classroom:id,name'])->get();
        $reservedAt = fn (CarbonImmutable $day, $slot) => $closures->filter(fn (ClassroomClosure $closure) => $closure->covers($day, $slot))->values();

        // Cupo por hora: aulas físicas (presencial) y virtuales (en línea) sin reserva, menos los clubs ya programados.
        $grid = $this->sessions->groupBy(fn (ClubSession $session) => $session->date->toDateString().'|'.$session->schedule_slot_id);
        $free = function (CarbonImmutable $day, $slot) use ($physical, $virtual, $reservedAt, $grid): int {
            $reserved = $reservedAt($day, $slot)->pluck('classroom_id');
            $sessions = $grid->get($day->toDateString().'|'.$slot->id, collect());

            return max(0, $physical->whereNotIn('id', $reserved)->count() - $sessions->where('modality', ClubModality::InPerson)->count())
                + max(0, $virtual->whereNotIn('id', $reserved)->count() - $sessions->where('modality', ClubModality::Online)->count());
        };

        // Clubs cuyo teacher ya no tiene esa hora en el panel armado (p. ej. por una falta sin cubrir).
        $linked = TeacherAvailability::query()->where('school_id', $school->id)->whereIn('club_session_id', $this->sessions->modelKeys())->pluck('club_session_id');
        $armed = collect($weeks)->mapWithKeys(fn (array $days) => [$days[0]->toDateString() => $planner->isGenerated($school, $days[0])]);
        $orphans = $this->sessions->filter(fn (ClubSession $session) => ($armed[$session->date->startOfWeek(CarbonInterface::MONDAY)->toDateString()] ?? false)
            && ! $linked->contains($session->id))->modelKeys();

        // Horas programadas por abreviatura en el mes (para la leyenda).
        $codeHours = $this->sessions->groupBy(fn (ClubSession $session) => $session->club_id.'|'.$session->code)
            ->map(fn (Collection $items) => $items->sum(fn (ClubSession $session) => self::hours($session)));

        return view('livewire.teachers.club-schedule', [
            'school' => $school,
            'monthStart' => $first,
            'weeks' => $weeks,
            'scheduleSlots' => $slots,
            'holidays' => $holidays,
            'grid' => $grid,
            'free' => $free,
            'reservedAt' => $reservedAt,
            'physical' => $physical,
            'virtual' => $virtual,
            'orphans' => $orphans,
            'codeHours' => $codeHours,
            'summaries' => $this->summaryTab === '' ? [] : [$this->summary($this->month), $this->summary($first->subMonth()->format('Y-m'))],
            'planner' => $planner,
            'canManage' => auth()->user()->can('manage', ClubSession::class),
            'modalities' => collect(ClubModality::cases())->mapWithKeys(fn (ClubModality $modality) => [$modality->value => $modality->label()])->all(),
            'slotLabel' => $this->slotId ? $slots->firstWhere('id', $this->slotId)?->range() : null,
        ])->title(__('clubs.title'));
    }
}
