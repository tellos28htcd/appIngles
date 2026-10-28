<?php

namespace App\Livewire\Teachers;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Models\AvailabilityWeek;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Teachers → Disponibilidad de horarios. Igual a la hoja "H de Teacher":
 * por día, una columna por teacher; por hora, el número de aula. Se elige
 * mes y semana (histórico). La semana se arma con el horario base y las
 * aulas de la semana anterior; el administrador ajusta las aulas aquí.
 */
#[Layout('layouts.app')]
class AvailabilityPanel extends Component
{
    #[Url(as: 'mes', except: '')]
    public string $month = '';

    #[Url(as: 'semana', except: '')]
    public string $week = '';

    /** Teacher cuyo horario base se edita en la ventana (clic en su nombre). */
    public ?int $editingScheduleTeacherId = null;

    public function mount(): void
    {
        if (auth()->user()->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('availability.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $this->authorize('viewAny', AvailabilityWeek::class);
        $this->normalize();
    }

    public function updatedMonth(): void
    {
        $this->week = '';
        $this->normalize();
    }

    public function updatedWeek(): void
    {
        $this->normalize();
    }

    /** Mes y semana válidos: por defecto la semana actual. */
    private function normalize(): void
    {
        $today = CarbonImmutable::now(config('appingles.timezone'));

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = $today->format('Y-m');
        }

        $mondays = $this->mondays();
        $current = $today->startOfWeek(CarbonInterface::MONDAY)->toDateString();

        if (! in_array($this->week, $mondays, true)) {
            $this->week = in_array($current, $mondays, true) ? $current : $mondays[0];
        }
    }

    /** Lunes de las semanas que tocan el mes elegido. @return list<string> */
    private function mondays(): array
    {
        $first = CarbonImmutable::parse($this->month.'-01');
        $mondays = [];

        for ($monday = $first->startOfWeek(CarbonInterface::MONDAY); $monday->lte($first->endOfMonth()); $monday = $monday->addWeek()) {
            $mondays[] = $monday->toDateString();
        }

        return $mondays;
    }

    private function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    public function previousWeek(): void
    {
        $this->goTo(CarbonImmutable::parse($this->week)->subWeek());
    }

    public function nextWeek(): void
    {
        $this->goTo(CarbonImmutable::parse($this->week)->addWeek());
    }

    private function goTo(CarbonImmutable $monday): void
    {
        // La semana pertenece al mes de su jueves (como en el calendario ISO).
        $this->month = $monday->addDays(3)->format('Y-m');
        $this->week = $monday->toDateString();
        $this->normalize();
    }

    public function generate(AvailabilityPlanner $planner): void
    {
        $this->authorize('manage', AvailabilityWeek::class);
        $planner->generateWeek($this->school(), CarbonImmutable::parse($this->week), auth()->id());

        $this->dispatch('toast', type: 'success', message: __('availability.messages.week_generated'));
    }

    public function setClassroom(int $cellId, ?int $classroomId, AvailabilityPlanner $planner): void
    {
        $this->authorize('manage', AvailabilityWeek::class);

        $cell = TeacherAvailability::query()->where('school_id', auth()->user()->school_id)->where('is_absent', false)->findOrFail($cellId);

        try {
            $planner->setClassroom($cell, $classroomId);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());
        }
    }

    public function editSchedule(int $teacherId): void
    {
        $teacher = Teacher::query()->where('school_id', auth()->user()->school_id)->findOrFail($teacherId);
        $this->authorize('editSchedule', $teacher);
        $this->editingScheduleTeacherId = $teacher->id;
    }

    /** Al guardar el horario base se cierra la ventana; el panel se vuelve a dibujar con las semanas recalculadas. */
    #[On('base-schedule-saved')]
    public function scheduleSaved(): void
    {
        $this->editingScheduleTeacherId = null;
    }

    public function render(AvailabilityPlanner $planner): View
    {
        $school = $this->school();
        $monday = CarbonImmutable::parse($this->week);
        $days = $planner->days($school, $monday);
        $slots = $planner->slots($school);
        $generated = $planner->isGenerated($school, $monday);

        $cells = TeacherAvailability::query()->where('school_id', $school->id)->where('is_absent', false)
            ->whereBetween('date', [$days[0]->toDateString(), end($days)->toDateString()])
            ->with(['classroom:id,name', 'clubSession:id,club_id,code,modality', 'clubSession.club:id,codes', 'coordinatorSession:id,coordinator_activity_id', 'coordinatorSession.activity:id,name,panel_mark'])
            ->get();

        // Columnas: los teachers del panel y, en semanas pasadas, también quien tuvo horas aunque hoy ya no esté.
        $teachers = Teacher::query()->where('school_id', $school->id)
            ->where(fn ($query) => $query->whereKey($cells->pluck('teacher_id')->unique())
                ->orWhere(fn ($q) => $q->inPanel()))
            ->orderByRaw('panel_number is null')->orderBy('panel_number')->orderBy('first_name')
            ->get();

        // Cada teacher aparece desde su fecha de ingreso: antes no tiene columna ni horas en el panel.
        $hired = fn (Teacher $teacher, string $day): bool => $teacher->hired_on === null || $teacher->hired_on->toDateString() <= $day;
        $teachers = $teachers->filter(fn (Teacher $teacher) => $hired($teacher, end($days)->toDateString()))->values();
        $byId = $teachers->keyBy('id');
        $cells = $cells->filter(fn (TeacherAvailability $cell) => $byId->has($cell->teacher_id) && $hired($byId->get($cell->teacher_id), $cell->date->toDateString()))->values();
        $dayTeachers = collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [
            $day->toDateString() => $teachers->filter(fn (Teacher $teacher) => $hired($teacher, $day->toDateString()))->values(),
        ])->all();

        $grid = $cells->keyBy(fn (TeacherAvailability $cell) => $cell->date->toDateString().'|'.$cell->schedule_slot_id.'|'.$cell->teacher_id);
        $perDay = $cells->groupBy(fn (TeacherAvailability $cell) => $cell->date->toDateString().'|'.$cell->teacher_id)->map->count();
        $weekly = $cells->groupBy('teacher_id')->map->count();

        // Gráfica (como "x̄T" del Excel): por cada hora se cuentan las aulas asignadas de la semana y se dividen
        // entre 5 (lunes a viernes), o entre 6 si ese sábado hay aulas a esa hora (y 7 si también el domingo).
        // La Lista de espera no cuenta: no es un teacher que dé clase.
        $holidays = $planner->holidays($school, $days);
        $waitlist = $teachers->where('is_waitlist', true)->pluck('id');
        $chartCells = $cells->whereNotIn('teacher_id', $waitlist)->whereNotNull('classroom_id');
        $average = $slots->mapWithKeys(function ($slot) use ($days, $chartCells) {
            $slotCells = $chartCells->where('schedule_slot_id', $slot->id);
            $weekend = collect($days)->filter(fn (CarbonImmutable $day) => $day->dayOfWeekIso >= 6
                && $slotCells->contains(fn ($cell) => $cell->date->toDateString() === $day->toDateString()))->count();

            return [$slot->id => round($slotCells->count() / (5 + $weekend), 1)];
        });

        // Horas con incidencia que nadie cubre: se marcan "A" (ausente) en el panel.
        $covered = $cells->whereNotNull('covered_teacher_id')
            ->map(fn (TeacherAvailability $cell) => $cell->date->toDateString().'|'.$cell->schedule_slot_id.'|'.$cell->covered_teacher_id)->flip();
        $absences = TeacherAvailability::query()->where('school_id', $school->id)->where('is_absent', true)->whereNotNull('teacher_incident_id')
            ->whereBetween('date', [$days[0]->toDateString(), end($days)->toDateString()])
            ->get(['date', 'schedule_slot_id', 'teacher_id'])
            ->map(fn (TeacherAvailability $cell) => $cell->date->toDateString().'|'.$cell->schedule_slot_id.'|'.$cell->teacher_id)
            ->reject(fn (string $key) => $covered->has($key))
            ->flip();

        $classrooms = Classroom::query()->ofCatalog($school->id)->where('is_active', true)->orderByRaw('CAST(name AS UNSIGNED)')->orderBy('name')->get(['id', 'name']);

        return view('livewire.teachers.availability-panel', [
            'school' => $school,
            'days' => $days,
            'absences' => $absences,
            'applies' => $slots->mapWithKeys(fn ($slot) => [$slot->id => collect($days)->mapWithKeys(fn (CarbonImmutable $day) => [$day->toDateString() => $planner->slotAppliesOn($slot, $day->dayOfWeekIso)])->all()])->all(),
            'scheduleSlots' => $slots,
            'teachers' => $teachers,
            'dayTeachers' => $dayTeachers,
            'grid' => $grid,
            'perDay' => $perDay,
            'weekly' => $weekly,
            'average' => $average,
            'maxAverage' => max(1, (float) $average->max()),
            'generated' => $generated,
            'holidays' => $holidays,
            'canManage' => auth()->user()->can('manage', AvailabilityWeek::class),
            'classrooms' => $classrooms,
            'classroomNumbers' => $this->classroomNumbers($classrooms),
            'monthOptions' => $this->monthOptions(),
            'weekOptions' => collect($this->mondays())->mapWithKeys(fn (string $monday) => [$monday => __('availability.week_option', [
                'from' => CarbonImmutable::parse($monday)->translatedFormat('j M'),
                'to' => CarbonImmutable::parse($monday)->addDays(5)->translatedFormat('j M'),
            ])])->all(),
        ])->title(__('availability.panel_title'));
    }

    /** Número (para el color) de cada aula: su nombre si es número, si no, su posición. @return array<int, int> */
    private function classroomNumbers(Collection $classrooms): array
    {
        return $classrooms->values()->mapWithKeys(fn (Classroom $classroom, int $index) => [
            $classroom->id => ctype_digit((string) $classroom->name) ? (int) $classroom->name : $index + 1,
        ])->all();
    }

    /** Meses para el histórico: desde la primera semana armada hasta 3 meses adelante. @return array<string, string> */
    private function monthOptions(): array
    {
        $first = AvailabilityWeek::query()->where('school_id', auth()->user()->school_id)->min('starts_on');
        $start = CarbonImmutable::parse($first ?? 'now')->startOfMonth()->min(CarbonImmutable::now()->startOfMonth());
        $end = CarbonImmutable::now()->addMonths(3)->startOfMonth()->max(CarbonImmutable::parse($this->month.'-01'));
        $options = [];

        for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
            $options[$month->format('Y-m')] = ucfirst($month->translatedFormat('F Y'));
        }

        return array_reverse($options, true);
    }
}
