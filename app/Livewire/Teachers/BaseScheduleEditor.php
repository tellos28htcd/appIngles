<?php

namespace App\Livewire\Teachers;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Models\AvailabilityWeek;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Cuadrícula del horario base del teacher: qué horas del catálogo trabaja cada
 * día. Se usa en la página "Horario base" y en la ventana que se abre desde el
 * panel de disponibilidad. Tope: 48 h por semana (Lista de espera no tiene tope).
 * Al guardar se recalculan las semanas ya armadas desde hoy y se avisa con el
 * evento "base-schedule-saved".
 */
class BaseScheduleEditor extends Component
{
    #[Locked]
    public int $teacherId;

    /** "día-bloque" => true. @var array<string, bool> */
    public array $selected = [];

    public function mount(int $teacherId): void
    {
        $teacher = Teacher::query()->where('school_id', auth()->user()->school_id)->findOrFail($teacherId);
        $this->authorize('editSchedule', $teacher);
        $this->teacherId = $teacher->id;
        $this->selected = $teacher->scheduleBlocks()->get()
            ->mapWithKeys(fn (TeacherScheduleBlock $block) => [$block->weekday.'-'.$block->schedule_slot_id => true])
            ->all();
    }

    private function teacher(): Teacher
    {
        return Teacher::query()->where('school_id', auth()->user()->school_id)->findOrFail($this->teacherId);
    }

    /** Marca o desmarca todas las horas de un tramo (día completo o una hora en todos los días). */
    public function toggleMany(string $keys): void
    {
        $keys = array_filter(explode(',', $keys));
        $allOn = collect($keys)->every(fn (string $key) => ! empty($this->selected[$key]));

        foreach ($keys as $key) {
            $this->selected[$key] = ! $allOn;
        }
    }

    public function save(AvailabilityPlanner $planner): void
    {
        $teacher = $this->teacher();
        $this->authorize('editSchedule', $teacher);
        $school = School::findOrFail($teacher->school_id);

        $slots = $planner->slots($school)->keyBy('id');
        $days = $planner->weekdays($school);
        // Solo las horas de un turno que aplica ese día (p. ej. el turno Domingo solo el domingo).
        $blocks = collect($this->selected)->filter()->keys()
            ->map(fn (string $key) => array_map('intval', explode('-', $key)))
            ->filter(fn (array $pair) => in_array($pair[0], $days, true) && $slots->has($pair[1]) && $planner->slotAppliesOn($slots->get($pair[1]), $pair[0]))
            ->values();

        $limit = $planner->weeklyLimit($teacher);
        if ($limit !== null && $blocks->count() > $limit) {
            throw ValidationException::withMessages(['selected' => __('availability.validation.schedule_weekly', ['hours' => $blocks->count(), 'limit' => $limit])]);
        }

        DB::transaction(function () use ($teacher, $blocks, $planner, $school): void {
            $teacher->scheduleBlocks()->delete();
            $now = now();
            TeacherScheduleBlock::insert($blocks->map(fn (array $pair) => [
                'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id, 'weekday' => $pair[0], 'schedule_slot_id' => $pair[1],
                'created_at' => $now, 'updated_at' => $now,
            ])->all());

            // Las semanas ya armadas desde hoy se actualizan; las pasadas quedan como histórico.
            $lastWeek = AvailabilityWeek::query()->where('school_id', $school->id)->max('starts_on');
            $today = now(config('appingles.timezone'))->startOfDay();

            if ($lastWeek !== null && $today->lte($lastWeek.' +6 days')) {
                $planner->rebuildRange($school, $today, CarbonImmutable::parse($lastWeek)->addDays(6));
            }
        });

        $this->dispatch('base-schedule-saved', teacherId: $teacher->id);
        $this->dispatch('toast', type: 'success', message: __('availability.messages.schedule_saved'));
    }

    public function render(AvailabilityPlanner $planner): View
    {
        $teacher = $this->teacher();
        $school = School::findOrFail($teacher->school_id);
        $days = $planner->weekdays($school);
        $slots = $planner->slots($school);
        $selected = collect($this->selected)->filter();

        return view('livewire.teachers.base-schedule-editor', [
            'teacher' => $teacher,
            'scheduleSlots' => $slots,
            'applies' => $slots->mapWithKeys(fn ($slot) => [$slot->id => collect($days)->filter(fn (int $day) => $planner->slotAppliesOn($slot, $day))->values()->all()])->all(),
            'days' => $days,
            'dailyHours' => collect($days)->mapWithKeys(fn (int $day) => [$day => $selected->keys()->filter(fn (string $key) => str_starts_with($key, $day.'-'))->count()])->all(),
            'weeklyHours' => $selected->count(),
            'dailyReference' => $teacher->is_waitlist ? null : ($teacher->contract_type?->dailyHours() ?? 8),
            'weeklyLimit' => $planner->weeklyLimit($teacher),
            'dayNames' => collect($days)->mapWithKeys(fn (int $day) => [$day => now()->startOfWeek(CarbonInterface::MONDAY)->addDays($day - 1)->translatedFormat('l')])->all(),
        ]);
    }
}
