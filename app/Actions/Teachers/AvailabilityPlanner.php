<?php

namespace App\Actions\Teachers;

use App\Enums\ContractType;
use App\Models\AvailabilityWeek;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherIncident;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Panel de disponibilidad de teachers (teacher × día × hora → aula).
 *
 * - Una semana se arma con el horario base de cada teacher; el aula de cada
 *   hora se toma de la semana anterior (la primera vez se asigna en el panel).
 * - Las incidencias quitan las horas del teacher; "Cubre horas" se las pasa
 *   a quien cubre, en la misma aula.
 * - Un aula no tiene dos teachers a la misma hora (RN-05) y un teacher no
 *   pasa de 48 h por semana (Lista de espera no cuenta).
 * - Los días festivos de la escuela no tienen disponibilidad (RN-22).
 */
final class AvailabilityPlanner
{
    /** Bloques de horario activos de la escuela, en orden. @return Collection<int, ScheduleSlot> */
    public function slots(School $school): Collection
    {
        return ScheduleSlot::withoutGlobalScope('school')->where('school_id', $school->id)
            ->where('is_active', true)->orderBy('starts_at')->get();
    }

    /** Días que se muestran en la semana: lunes a sábado, y domingo si la escuela trabaja domingos. @return list<CarbonImmutable> */
    public function days(School $school, CarbonInterface $monday): array
    {
        $monday = CarbonImmutable::parse($monday)->startOfWeek(CarbonInterface::MONDAY);
        $count = $school->works_sundays ? 7 : 6;

        return array_map(fn (int $offset) => $monday->addDays($offset), range(0, $count - 1));
    }

    /** Teachers del panel de la escuela (activos y con número). @return Collection<int, Teacher> */
    public function teachers(School $school): Collection
    {
        return Teacher::withoutGlobalScope('school')->where('school_id', $school->id)->inPanel()->get();
    }

    public function isGenerated(School $school, CarbonInterface $monday): bool
    {
        return AvailabilityWeek::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereDate('starts_on', CarbonImmutable::parse($monday)->toDateString())->exists();
    }

    /** Arma la semana (si no existe): horario base + aulas de la semana anterior − incidencias. */
    public function generateWeek(School $school, CarbonInterface $monday, ?int $userId = null): void
    {
        $monday = CarbonImmutable::parse($monday)->startOfWeek(CarbonInterface::MONDAY);

        DB::transaction(function () use ($school, $monday, $userId): void {
            if ($this->isGenerated($school, $monday)) {
                return;
            }

            AvailabilityWeek::withoutGlobalScope('school')->create([
                'school_id' => $school->id, 'starts_on' => $monday->toDateString(), 'user_id' => $userId,
            ]);

            foreach ($this->days($school, $monday) as $day) {
                $this->rebuildDay($school, $day);
            }
        });
    }

    /** Vuelve a calcular las semanas ya armadas que tocan esas fechas (al guardar o borrar una incidencia). */
    public function rebuildRange(School $school, CarbonInterface $from, CarbonInterface $to): void
    {
        $day = CarbonImmutable::parse($from)->startOfDay();
        $last = CarbonImmutable::parse($to)->startOfDay();

        DB::transaction(function () use ($school, $day, $last): void {
            for (; $day->lte($last); $day = $day->addDay()) {
                if ($this->isGenerated($school, $day->startOfWeek(CarbonInterface::MONDAY))) {
                    $this->rebuildDay($school, $day);
                }
            }
        });
    }

    /**
     * Recalcula las celdas de un día. Conserva el aula que ya tenía cada hora
     * (o la de la semana anterior), así no se pierden los ajustes del panel.
     */
    public function rebuildDay(School $school, CarbonInterface $date): void
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $day = $date->toDateString();
        $cells = TeacherAvailability::withoutGlobalScope('school')->where('school_id', $school->id);

        // Aula de cada hora propia (si estaba ausente, la que tenía guardada).
        $classroomMap = fn (string $date) => (clone $cells)->whereDate('date', $date)->whereNull('covered_teacher_id')->get()
            ->mapWithKeys(fn (TeacherAvailability $cell) => [$cell->teacher_id.'|'.$cell->schedule_slot_id => $cell->classroom_id ?? $cell->held_classroom_id]);
        $existing = $classroomMap($day);
        $previous = $classroomMap($date->subWeek()->toDateString());

        (clone $cells)->whereDate('date', $day)->delete();

        if (Holiday::withoutGlobalScope('school')->where('school_id', $school->id)->covering($day)->exists()) {
            return;
        }

        $slots = $this->slots($school)->keyBy('id');
        $teachers = $this->teachers($school)->load('scheduleBlocks');
        $incidents = TeacherIncident::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)
            ->with(['fromSlot', 'toSlot'])->get();

        $classroomOf = fn (int $teacherId, int $slotId): ?int => $existing->get($teacherId.'|'.$slotId) ?? $previous->get($teacherId.'|'.$slotId);
        $absenceOf = fn (int $teacherId, ScheduleSlot $slot): ?TeacherIncident => $incidents->first(
            fn (TeacherIncident $incident) => $incident->teacher_id === $teacherId && ! $incident->type->isCoverage() && $incident->covers($date, $slot)
        );

        $rows = [];
        $usedClassrooms = [];
        $now = now();
        $add = function (int $teacherId, int $slotId, ?int $classroomId, ?int $coveredId = null, ?int $incidentId = null) use (&$rows, &$usedClassrooms, $school, $day, $now): void {
            // Un aula ocupada a esa hora se deja sin aula para que se reasigne en el panel.
            if ($classroomId !== null && isset($usedClassrooms[$slotId][$classroomId])) {
                $classroomId = null;
            }

            if ($classroomId !== null) {
                $usedClassrooms[$slotId][$classroomId] = true;
            }

            $rows[$teacherId.'|'.$slotId] = [
                'school_id' => $school->id, 'teacher_id' => $teacherId, 'date' => $day, 'schedule_slot_id' => $slotId,
                'classroom_id' => $classroomId, 'is_absent' => false, 'held_classroom_id' => null,
                'covered_teacher_id' => $coveredId, 'teacher_incident_id' => $incidentId,
                'created_at' => $now, 'updated_at' => $now,
            ];
        };
        // Hora con incidencia: queda oculta y guarda su aula (la usa quien cubre; regresa si se borra la incidencia).
        $absent = function (int $teacherId, int $slotId, ?int $heldClassroomId, int $incidentId) use (&$rows, $school, $day, $now): void {
            $rows[$teacherId.'|'.$slotId] = [
                'school_id' => $school->id, 'teacher_id' => $teacherId, 'date' => $day, 'schedule_slot_id' => $slotId,
                'classroom_id' => null, 'is_absent' => true, 'held_classroom_id' => $heldClassroomId,
                'covered_teacher_id' => null, 'teacher_incident_id' => $incidentId,
                'created_at' => $now, 'updated_at' => $now,
            ];
        };

        $scheduled = [];
        foreach ($teachers as $teacher) {
            foreach ($teacher->scheduleBlocks->where('weekday', $date->dayOfWeekIso) as $block) {
                $slot = $slots->get($block->schedule_slot_id);

                if ($slot !== null) {
                    $scheduled[$teacher->id][$slot->id] = true;

                    $absence = $absenceOf($teacher->id, $slot);
                    $absence === null
                        ? $add($teacher->id, $slot->id, $classroomOf($teacher->id, $slot->id))
                        : $absent($teacher->id, $slot->id, $classroomOf($teacher->id, $slot->id), $absence->id);
                }
            }
        }

        // "Cubre horas": las horas (que el cubierto tenía en su horario) pasan a quien cubre, en la misma aula.
        foreach ($incidents->filter(fn (TeacherIncident $incident) => $incident->type->isCoverage()) as $incident) {
            foreach ($slots as $slot) {
                if (! $incident->covers($date, $slot) || ! isset($scheduled[$incident->covered_teacher_id][$slot->id])
                    || isset($rows[$incident->teacher_id.'|'.$slot->id])) {
                    continue;
                }

                $add($incident->teacher_id, $slot->id, $classroomOf($incident->covered_teacher_id, $slot->id), $incident->covered_teacher_id, $incident->id);
            }
        }

        if ($rows !== []) {
            TeacherAvailability::withoutGlobalScope('school')->insert(array_values($rows));
        }
    }

    /** Asigna (o quita) el aula de una celda del panel. */
    public function setClassroom(TeacherAvailability $cell, ?int $classroomId): void
    {
        if ($classroomId !== null) {
            $classroom = Classroom::withoutGlobalScope('school')->where('school_id', $cell->school_id)->whereKey($classroomId)->first();

            if ($classroom === null) {
                throw ValidationException::withMessages(['classroom' => __('availability.validation.classroom')]);
            }

            $taken = TeacherAvailability::withoutGlobalScope('school')->where('school_id', $cell->school_id)
                ->whereDate('date', $cell->date->toDateString())->where('schedule_slot_id', $cell->schedule_slot_id)
                ->where('classroom_id', $classroomId)->whereKeyNot($cell->id)->with('teacher')->first();

            if ($taken !== null) {
                throw ValidationException::withMessages(['classroom' => __('availability.validation.classroom_taken', [
                    'classroom' => $classroom->name, 'teacher' => $taken->teacher->panelName(),
                ])]);
            }
        }

        $cell->update(['classroom_id' => $classroomId]);
    }

    /** Horas del teacher en la semana (celdas del panel). */
    public function weeklyHours(Teacher $teacher, CarbonInterface $monday): int
    {
        $monday = CarbonImmutable::parse($monday)->startOfWeek(CarbonInterface::MONDAY);

        return TeacherAvailability::withoutGlobalScope('school')->where('teacher_id', $teacher->id)->where('is_absent', false)
            ->whereBetween('date', [$monday->toDateString(), $monday->addDays(6)->toDateString()])->count();
    }

    /**
     * Revisa una incidencia "Cubre horas" antes de guardarla: quien cubre debe
     * estar libre a esas horas y no pasar de 48 h en ninguna semana.
     *
     * @return list<string> errores (vacío si es válida)
     */
    public function coverageProblems(School $school, TeacherIncident $incident): array
    {
        $teacher = Teacher::withoutGlobalScope('school')->with('scheduleBlocks')->findOrFail($incident->teacher_id);
        $covered = Teacher::withoutGlobalScope('school')->with('scheduleBlocks')->findOrFail($incident->covered_teacher_id);
        $slots = $this->slots($school);
        $incidents = TeacherIncident::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereIn('teacher_id', [$teacher->id, $covered->id])
            ->whereDate('starts_on', '<=', $incident->ends_on->toDateString())->whereDate('ends_on', '>=', $incident->starts_on->toDateString())
            ->when($incident->exists, fn ($query) => $query->whereKeyNot($incident->id))
            ->with(['fromSlot', 'toSlot'])->get();
        $incident->loadMissing(['fromSlot', 'toSlot']);

        $problems = [];
        $weekly = [];
        $limit = $this->weeklyLimit($teacher);

        for ($day = $incident->starts_on->toImmutable(); $day->lte($incident->ends_on); $day = $day->addDay()) {
            $monday = $day->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $weekly[$monday] ??= $this->projectedWeekHours($school, $teacher, CarbonImmutable::parse($monday), $incidents);

            foreach ($slots as $slot) {
                if (! $incident->covers($day, $slot) || ! $covered->scheduleBlocks->contains(fn ($block) => $block->weekday === $day->dayOfWeekIso && $block->schedule_slot_id === $slot->id)) {
                    continue;
                }

                $busy = $teacher->scheduleBlocks->contains(fn ($block) => $block->weekday === $day->dayOfWeekIso && $block->schedule_slot_id === $slot->id)
                    && ! $incidents->contains(fn (TeacherIncident $other) => $other->teacher_id === $teacher->id && ! $other->type->isCoverage() && $other->covers($day, $slot));

                if ($busy) {
                    $problems[] = __('availability.validation.cover_busy', [
                        'teacher' => $teacher->panelName(), 'date' => $day->translatedFormat('D j M'), 'hour' => substr($slot->starts_at, 0, 5),
                    ]);
                }

                $weekly[$monday]++;
            }
        }

        foreach ($weekly as $monday => $hours) {
            if ($limit !== null && $hours > $limit) {
                $problems[] = __('availability.validation.cover_weekly', [
                    'teacher' => $teacher->panelName(), 'week' => CarbonImmutable::parse($monday)->translatedFormat('j M'), 'hours' => $hours, 'limit' => $limit,
                ]);
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Horas que tendría el teacher en la semana según su horario base, sus
     * ausencias y las horas que ya cubre (sin depender de que la semana esté armada).
     *
     * @param  Collection<int, TeacherIncident>  $incidents
     */
    private function projectedWeekHours(School $school, Teacher $teacher, CarbonImmutable $monday, Collection $incidents): int
    {
        $slots = $this->slots($school);
        $hours = 0;

        foreach ($this->days($school, $monday) as $day) {
            foreach ($slots as $slot) {
                $own = $teacher->scheduleBlocks->contains(fn ($block) => $block->weekday === $day->dayOfWeekIso && $block->schedule_slot_id === $slot->id)
                    && ! $incidents->contains(fn (TeacherIncident $other) => $other->teacher_id === $teacher->id && ! $other->type->isCoverage() && $other->covers($day, $slot));
                $covering = $incidents->contains(fn (TeacherIncident $other) => $other->teacher_id === $teacher->id && $other->type->isCoverage() && $other->covers($day, $slot));

                $hours += ($own || $covering) ? 1 : 0;
            }
        }

        return $hours;
    }

    /** Tope semanal de horas (48); Lista de espera no tiene tope. */
    public function weeklyLimit(Teacher $teacher): ?int
    {
        return $teacher->is_waitlist ? null : ContractType::MAX_WEEKLY_HOURS;
    }
}
