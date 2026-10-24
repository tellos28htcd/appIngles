<?php

namespace App\Livewire\Teachers;

use App\Actions\Coordination\ScheduleCoordinatorHour;
use App\Actions\Teachers\AvailabilityPlanner;
use App\Models\CoordinatorActivity;
use App\Models\CoordinatorSession;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Teacher;
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
 * Teachers → Horas Coordinador: plan mensual de las actividades del coordinador.
 * Arriba, el catálogo con sus horas autorizadas (HA) y las programadas en el mes (HP);
 * abajo, una cuadrícula por semana (días × horas) donde se agrega la actividad en cada
 * hora. Las reglas las aplica ScheduleCoordinatorHour.
 */
#[Layout('layouts.app')]
class CoordinatorSchedule extends Component
{
    /** Valor del selector de teacher para las actividades que no ocupan la hora de un teacher. */
    public const COORDINATION = 'coordinacion';

    #[Url(as: 'mes', except: '')]
    public string $month = '';

    /** Pestaña del resumen abierta: teacher | activity ('' = colapsado). */
    #[Url(as: 'resumen', except: '')]
    public string $summaryTab = '';

    public ?int $sessionId = null;

    public ?bool $editing = null;

    public string $date = '';

    public ?int $slotId = null;

    public ?int $activityId = null;

    /** ID del teacher o "coordinacion". */
    public string $teacherKey = '';

    public bool $repeat = false;

    public function mount(): void
    {
        if (auth()->user()->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('availability.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $this->authorize('viewAny', CoordinatorSession::class);

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

    public function toggleSummary(string $tab): void
    {
        $this->summaryTab = $this->summaryTab === $tab || ! in_array($tab, ['teacher', 'activity'], true) ? '' : $tab;
    }

    public function add(string $date, int $slotId): void
    {
        $this->authorize('manage', CoordinatorSession::class);
        $this->resetValidation();
        $this->reset('sessionId', 'activityId', 'teacherKey', 'repeat');
        $this->date = CarbonImmutable::parse($date)->toDateString();
        $this->slotId = $slotId;
        $this->editing = true;
    }

    public function edit(int $sessionId): void
    {
        $this->authorize('manage', CoordinatorSession::class);
        $this->resetValidation();
        $session = $this->find($sessionId);

        $this->sessionId = $session->id;
        $this->date = $session->date->toDateString();
        $this->slotId = $session->schedule_slot_id;
        $this->activityId = $session->coordinator_activity_id;
        $this->teacherKey = $session->teacher_id ? (string) $session->teacher_id : self::COORDINATION;
        $this->repeat = $session->repeats;
        $this->editing = true;
    }

    /** Al cambiar la actividad cambia la lista: teachers (si ocupa su hora) o "Actividad Coordinación". */
    public function updatedActivityId(): void
    {
        $this->teacherKey = $this->selectedActivity()?->needsTeacher() ? '' : self::COORDINATION;
    }

    private function selectedActivity(): ?CoordinatorActivity
    {
        return $this->activityId ? $this->activities->firstWhere('id', $this->activityId) : null;
    }

    public function save(ScheduleCoordinatorHour $scheduler): void
    {
        $this->authorize('manage', CoordinatorSession::class);
        $this->validate([
            'activityId' => ['required', 'integer'],
            'teacherKey' => ['required', 'string'],
            'date' => ['required', 'date'],
            'slotId' => ['required', 'integer'],
        ], attributes: [
            'activityId' => mb_strtolower(__('coordination.fields.activity')),
            'teacherKey' => mb_strtolower(__('coordination.fields.teacher')),
        ]);

        $school = $this->school();
        $data = [
            'coordinator_activity_id' => $this->activityId,
            'teacher_id' => ctype_digit($this->teacherKey) ? (int) $this->teacherKey : null,
            'date' => $this->date,
            'schedule_slot_id' => $this->slotId,
            'repeats' => $this->repeat,
        ];

        try {
            $scheduler->save($school, $data, $this->sessionId ? $this->find($this->sessionId) : null, auth()->id());
        } catch (ValidationException $exception) {
            // El campo del teacher en pantalla es "teacherKey".
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn ($messages, $field) => [$field === 'teacherId' ? 'teacherKey' : $field => $messages])->all());
        }

        // Repetir: misma actividad (y teacher) el mismo día y hora de las semanas siguientes del mes.
        $skipped = [];
        if ($this->repeat) {
            $start = CarbonImmutable::parse($this->date);
            for ($day = $start->addWeek(); $day->month === $start->month; $day = $day->addWeek()) {
                $already = CoordinatorSession::query()->where('school_id', $school->id)->whereDate('date', $day->toDateString())
                    ->where('schedule_slot_id', $this->slotId)->where('coordinator_activity_id', $this->activityId)->exists();

                if ($already) {
                    continue;
                }

                try {
                    $scheduler->save($school, [...$data, 'date' => $day->toDateString()], null, auth()->id());
                } catch (ValidationException $exception) {
                    $skipped[] = $day->translatedFormat('j M').': '.collect($exception->errors())->flatten()->first();
                }
            }
        }

        $this->editing = null;
        unset($this->sessions);
        $this->dispatch('toast', ...($skipped === []
            ? ['type' => 'success', 'message' => __('coordination.messages.saved')]
            : ['type' => 'warning', 'message' => __('coordination.messages.saved_with_skips', ['days' => implode(' · ', $skipped)])]));
    }

    public function delete(ScheduleCoordinatorHour $scheduler): void
    {
        $this->authorize('manage', CoordinatorSession::class);
        $scheduler->delete($this->find((int) $this->sessionId));

        $this->editing = null;
        unset($this->sessions);
        $this->dispatch('toast', type: 'success', message: __('coordination.messages.deleted'));
    }

    private function find(int $sessionId): CoordinatorSession
    {
        return CoordinatorSession::query()->where('school_id', auth()->user()->school_id)->findOrFail($sessionId);
    }

    /** Actividades activas del catálogo de la escuela. @return Collection<int, CoordinatorActivity> */
    #[Computed]
    public function activities(): Collection
    {
        return CoordinatorActivity::query()->where('school_id', auth()->user()->school_id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return Collection<int, CoordinatorSession> */
    private function sessionsOf(string $month): Collection
    {
        $first = CarbonImmutable::parse($month.'-01');

        return CoordinatorSession::query()->where('school_id', auth()->user()->school_id)
            ->whereBetween('date', [$first->toDateString(), $first->endOfMonth()->toDateString()])
            ->with(['activity', 'teacher', 'slot'])->get();
    }

    /** @return Collection<int, CoordinatorSession> */
    #[Computed]
    public function sessions(): Collection
    {
        return $this->sessionsOf($this->month);
    }

    /** Opciones del teacher: los que tienen esa hora libre (si la actividad la ocupa) o "Actividad Coordinación". @return array<string, string> */
    #[Computed]
    public function teacherOptions(): array
    {
        $activity = $this->selectedActivity();

        if ($activity === null) {
            return [];
        }

        if (! $activity->needsTeacher()) {
            return [self::COORDINATION => __('coordination.coordination_option')];
        }

        $slot = $this->editingSlot();
        if ($slot === null) {
            return [];
        }

        // Si ocupa la hora: teachers con esa hora libre; si no: los que están trabajando a esa hora.
        $school = $this->school();
        $planner = app(AvailabilityPlanner::class);
        $date = CarbonImmutable::parse($this->date);
        $ids = $activity->uses_teacher_hour
            ? $planner->availableTeacherIds($school, $date, $slot, null, $this->sessionId)
            : $planner->workingTeacherIds($school, $date, $slot);

        return Teacher::query()->where('school_id', $school->id)->whereKey($ids)->where('is_waitlist', false)->orderBy('panel_number')->get()
            ->mapWithKeys(fn (Teacher $teacher) => [(string) $teacher->id => $teacher->panelName()])->all();
    }

    private function editingSlot(): ?ScheduleSlot
    {
        if (! $this->editing || $this->date === '' || $this->slotId === null) {
            return null;
        }

        return app(AvailabilityPlanner::class)->slots($this->school())->firstWhere('id', $this->slotId);
    }

    /**
     * Resumen de un mes por teacher y por actividad.
     *
     * @return array{label: string, teachers: list<array{name: string, hours: float}>, activities: list<array{name: string, hours: float}>, total: float}
     */
    private function summary(string $month): array
    {
        $sessions = $month === $this->month ? $this->sessions : $this->sessionsOf($month);

        return [
            'label' => Str::ucfirst(CarbonImmutable::parse($month.'-01')->translatedFormat('F Y')),
            'teachers' => $sessions->groupBy(fn (CoordinatorSession $session) => $session->teacher_id ?? 0)
                ->map(fn (Collection $items, $teacherId) => [
                    'name' => $teacherId ? ($items->first()->teacher?->panelName() ?? '—') : __('coordination.coordination_option'),
                    'order' => $teacherId ? ($items->first()->teacher?->panel_number ?? 999) : 0,
                    'hours' => $items->sum(fn (CoordinatorSession $session) => $session->hours()),
                ])->sortBy('order')->values()->all(),
            'activities' => $sessions->groupBy('coordinator_activity_id')
                ->map(fn (Collection $items) => [
                    'name' => $items->first()->activity?->name ?? '—',
                    'order' => $items->first()->activity?->sort_order ?? 0,
                    'hours' => $items->sum(fn (CoordinatorSession $session) => $session->hours()),
                ])->sortBy('order')->values()->all(),
            'total' => $sessions->sum(fn (CoordinatorSession $session) => $session->hours()),
        ];
    }

    public function render(AvailabilityPlanner $planner): View
    {
        $school = $this->school();
        $first = CarbonImmutable::parse($this->month.'-01');
        $slots = $planner->slots($school);

        $weeks = [];
        for ($monday = $first->startOfWeek(CarbonInterface::MONDAY); $monday->lte($first->endOfMonth()); $monday = $monday->addWeek()) {
            $weeks[] = $planner->days($school, $monday);
        }

        // HA (autorizadas) y HP (programadas en el mes) por actividad.
        $programmed = $this->sessions->groupBy('coordinator_activity_id')
            ->map(fn (Collection $items) => $items->sum(fn (CoordinatorSession $session) => $session->hours()));
        $catalog = $this->activities->map(fn (CoordinatorActivity $activity) => [
            'activity' => $activity,
            'authorized' => (float) $activity->authorized_hours,
            'programmed' => (float) ($programmed[$activity->id] ?? 0),
        ]);

        return view('livewire.teachers.coordinator-schedule', [
            'monthStart' => $first,
            'weeks' => $weeks,
            'scheduleSlots' => $slots,
            'holidays' => $planner->holidays($school, collect($weeks)->flatten(1)->all()),
            'grid' => $this->sessions->keyBy(fn (CoordinatorSession $session) => $session->date->toDateString().'|'.$session->schedule_slot_id),
            'catalog' => $catalog,
            'totalAuthorized' => $catalog->sum('authorized'),
            'totalProgrammed' => $catalog->sum('programmed'),
            'allMatch' => $catalog->every(fn (array $row) => abs($row['authorized'] - $row['programmed']) < 0.001),
            'summaries' => $this->summaryTab === '' ? [] : [$this->summary($this->month), $this->summary($first->subMonth()->format('Y-m'))],
            'planner' => $planner,
            'canManage' => auth()->user()->can('manage', CoordinatorSession::class),
            'slotLabel' => $this->slotId ? $slots->firstWhere('id', $this->slotId)?->range() : null,
            'activityOptions' => $this->activities->mapWithKeys(fn (CoordinatorActivity $activity) => [$activity->id => $activity->name])->all(),
        ])->title(__('coordination.title'));
    }
}
