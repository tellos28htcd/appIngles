<?php

namespace App\Actions\Teachers;

use App\Actions\Settings\EnsureOfficialHolidays;
use App\Enums\ContractType;
use App\Enums\TeacherIncidentType;
use App\Enums\TeacherStatus;
use App\Models\AvailabilityWeek;
use App\Models\Classroom;
use App\Models\ClubSession;
use App\Models\CoordinatorActivity;
use App\Models\CoordinatorSession;
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
    /**
     * Fecha de calendario (sin hora ni zona horaria): "hoy" puede llegar con la
     * hora de la escuela (America/Mexico_City) y las fechas guardadas están en UTC.
     */
    private static function day(CarbonInterface|string $value): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::parse($value)->toDateString());
    }

    /** Bloques de horario activos de la escuela, en orden. @return Collection<int, ScheduleSlot> */
    public function slots(School $school): Collection
    {
        return ScheduleSlot::withoutGlobalScope('school')->where('school_id', $school->id)
            ->where('is_active', true)->with('shift')->orderBy('starts_at')->get();
    }

    /** ¿El bloque se trabaja ese día de la semana? (según los días de su turno; 1 = lunes … 7 = domingo). */
    public function slotAppliesOn(ScheduleSlot $slot, int $weekday): bool
    {
        return $slot->shift?->appliesOn($weekday) ?? $weekday <= 6;
    }

    /** Días de la semana de la escuela (1 … 6, y 7 si trabaja domingos o tiene un turno con domingo). @return list<int> */
    public function weekdays(School $school): array
    {
        $sunday = $school->works_sundays || $this->slots($school)->contains(fn (ScheduleSlot $slot) => $this->slotAppliesOn($slot, 7));

        return range(1, $sunday ? 7 : 6);
    }

    /** Días que se muestran en la semana: lunes a sábado, y domingo si la escuela lo trabaja. @return list<CarbonImmutable> */
    public function days(School $school, CarbonInterface $monday): array
    {
        $monday = self::day($monday)->startOfWeek(CarbonInterface::MONDAY);
        $count = count($this->weekdays($school));

        return array_map(fn (int $offset) => $monday->addDays($offset), range(0, $count - 1));
    }

    /** Días sin clase de la semana: fecha => nombre del festivo. @param list<CarbonImmutable> $days @return array<string, string> */
    public function holidays(School $school, array $days): array
    {
        $names = [];
        $holidays = Holiday::withoutGlobalScope('school')->where('school_id', $school->id)->where('is_active', true)
            ->whereDate('starts_on', '<=', end($days)->toDateString())->whereDate('ends_on', '>=', $days[0]->toDateString())->get();

        foreach ($days as $day) {
            $holiday = $holidays->first(fn (Holiday $holiday) => $day->betweenIncluded($holiday->starts_on, $holiday->ends_on));

            if ($holiday !== null) {
                $names[$day->toDateString()] = $holiday->name;
            }
        }

        return $names;
    }

    /** Teachers del panel de la escuela (activos y con número). @return Collection<int, Teacher> */
    public function teachers(School $school): Collection
    {
        return Teacher::withoutGlobalScope('school')->where('school_id', $school->id)->inPanel()->get();
    }

    /**
     * Teachers del panel que trabajan ese día: desde su fecha de ingreso y según sus incidencias de
     * Baja y Reingreso (la baja cuenta desde su fecha; el reingreso también). Sin esas incidencias, los activos.
     *
     * @return Collection<int, Teacher>
     */
    public function teachersOn(School $school, CarbonInterface $date): Collection
    {
        $day = self::day($date);
        $statusTypes = [TeacherIncidentType::Termination, TeacherIncidentType::Rehire];

        return Teacher::withoutGlobalScope('school')->where('school_id', $school->id)->whereNotNull('panel_number')
            ->whereIn('status', [TeacherStatus::Active, TeacherStatus::Terminated])
            ->with(['incidents' => fn ($query) => $query->withoutGlobalScope('school')->whereIn('type', $statusTypes)->orderBy('starts_on')->orderBy('id')])
            ->orderBy('panel_number')->get()
            ->filter(function (Teacher $teacher) use ($day): bool {
                // Antes de su fecha de ingreso no aparece.
                if ($teacher->hired_on !== null && $teacher->hired_on->gt($day)) {
                    return false;
                }

                $last = $teacher->incidents->filter(fn (TeacherIncident $incident) => $incident->starts_on->lte($day))->last();

                if ($last !== null) {
                    return $last->type === TeacherIncidentType::Rehire;
                }

                // Antes de su primera baja trabajaba; antes de un reingreso (sin baja registrada), no.
                $next = $teacher->incidents->first();

                return $next !== null ? $next->type === TeacherIncidentType::Termination : $teacher->status === TeacherStatus::Active;
            })
            ->values();
    }

    public function isGenerated(School $school, CarbonInterface $monday): bool
    {
        return AvailabilityWeek::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereDate('starts_on', CarbonImmutable::parse($monday)->toDateString())->exists();
    }

    /** Arma la semana (si no existe): horario base + aulas de la semana anterior − incidencias. */
    public function generateWeek(School $school, CarbonInterface $monday, ?int $userId = null): void
    {
        $monday = self::day($monday)->startOfWeek(CarbonInterface::MONDAY);

        // Los festivos oficiales del año (LFT) se generan si la escuela aún no los tiene.
        foreach (array_unique([$monday->year, $monday->addDays(6)->year]) as $year) {
            app(EnsureOfficialHolidays::class)->handle($school, $year);
        }

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
        $day = self::day($from);
        $last = self::day($to);

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
        $this->syncDay($school, self::day($date)->toDateString(), $this->dayRows($school, $date));
    }

    /**
     * Teachers que pueden dar una hora ese día (para un club o una actividad del coordinador
     * que ocupa su hora): la tienen en su horario base o la cubren por incidencia, sin ausencia,
     * festivo, club ni actividad del coordinador (salvo la que se está editando).
     *
     * @return list<int>
     */
    public function availableTeacherIds(School $school, CarbonInterface $date, ScheduleSlot $slot, ?int $exceptClubSessionId = null, ?int $exceptCoordinatorSessionId = null): array
    {
        return collect($this->dayRows($school, $date))
            ->filter(fn (array $row) => $row['schedule_slot_id'] === $slot->id && ! $row['is_absent']
                && ($row['club_session_id'] === null || $row['club_session_id'] === $exceptClubSessionId)
                && ($row['coordinator_session_id'] === null || $row['coordinator_session_id'] === $exceptCoordinatorSessionId))
            ->pluck('teacher_id')->values()->all();
    }

    /**
     * Teachers que están trabajando a esa hora (dando clase, club o con revisión): para las
     * actividades del coordinador que se hacen en su clase (p. ej. Evaluación de Sesión).
     *
     * @return list<int>
     */
    public function workingTeacherIds(School $school, CarbonInterface $date, ScheduleSlot $slot): array
    {
        return collect($this->dayRows($school, $date))
            ->filter(fn (array $row) => $row['schedule_slot_id'] === $slot->id && ! $row['is_absent'])
            ->pluck('teacher_id')->values()->all();
    }

    /**
     * Celdas que le tocan al día (sin guardarlas).
     *
     * @return array<string, array<string, mixed>> "teacher|bloque" => valores
     */
    private function dayRows(School $school, CarbonInterface $date): array
    {
        $date = self::day($date);
        $day = $date->toDateString();
        $cells = TeacherAvailability::withoutGlobalScope('school')->where('school_id', $school->id);

        // Aula de cada hora propia (si estaba ausente, la que tenía guardada).
        $classroomMap = fn (string $date) => (clone $cells)->whereDate('date', $date)->whereNull('covered_teacher_id')->get()
            ->mapWithKeys(fn (TeacherAvailability $cell) => [$cell->teacher_id.'|'.$cell->schedule_slot_id => $cell->classroom_id ?? $cell->held_classroom_id]);
        $existing = $classroomMap($day);
        // Aulas del mismo día de la última semana armada (aunque no sea la inmediata anterior).
        $lastSameWeekday = (clone $cells)->whereDate('date', '<', $day)
            ->whereRaw('MOD(DATEDIFF(?, `date`), 7) = 0', [$day])->max('date');
        $previous = $lastSameWeekday === null ? collect() : $classroomMap(CarbonImmutable::parse($lastSameWeekday)->toDateString());

        // Día festivo: sin disponibilidad; las horas quedan ocultas con su aula por si se quita el festivo.
        $isHoliday = Holiday::withoutGlobalScope('school')->where('school_id', $school->id)->covering($day)->exists();

        $slots = $this->slots($school)->keyBy('id');
        $teachers = $this->teachersOn($school, $date)->load('scheduleBlocks');
        $incidents = TeacherIncident::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)
            ->with(['fromSlot', 'toSlot'])->get();

        $classroomOf = fn (int $teacherId, int $slotId): ?int => $existing->get($teacherId.'|'.$slotId) ?? $previous->get($teacherId.'|'.$slotId);
        $absenceOf = fn (int $teacherId, ScheduleSlot $slot): ?TeacherIncident => $incidents->first(
            fn (TeacherIncident $incident) => $incident->teacher_id === $teacherId && $incident->type->isAbsence() && $incident->covers($date, $slot)
        );

        // Clubs del día: su hora usa el aula audiovisual y deja de estar disponible para clases normales.
        // Si el teacher del club falta y alguien lo cubre, el club pasa a quien cubre.
        $clubs = ClubSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $day)->get()
            ->keyBy(fn (ClubSession $session) => $session->teacher_id.'|'.$session->schedule_slot_id);

        // Actividades del coordinador que ocupan la hora del teacher (Revisión de Planeaciones): sin aula,
        // no sirven para clases ni clubs y en el panel llevan su letra.
        $reviews = CoordinatorSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $day)
            ->whereNotNull('teacher_id')
            ->whereIn('coordinator_activity_id', CoordinatorActivity::withoutGlobalScope('school')->where('uses_teacher_hour', true)->select('id'))
            ->get()
            ->keyBy(fn (CoordinatorSession $session) => $session->teacher_id.'|'.$session->schedule_slot_id);

        $rows = [];
        $usedClassrooms = [];
        foreach ($clubs as $session) {
            $usedClassrooms[$session->schedule_slot_id][$session->classroom_id] = true;
        }
        $now = now();
        $add = function (int $teacherId, int $slotId, ?int $classroomId, ?int $coveredId = null, ?int $incidentId = null) use (&$rows, &$usedClassrooms, $school, $day, $now, $clubs, $reviews): void {
            $review = $reviews->get($teacherId.'|'.$slotId) ?? ($coveredId !== null ? $reviews->get($coveredId.'|'.$slotId) : null);

            if ($review !== null) {
                $rows[$teacherId.'|'.$slotId] = [
                    'school_id' => $school->id, 'teacher_id' => $teacherId, 'date' => $day, 'schedule_slot_id' => $slotId,
                    'classroom_id' => null, 'is_absent' => false, 'held_classroom_id' => null,
                    'covered_teacher_id' => $coveredId, 'teacher_incident_id' => $incidentId, 'club_session_id' => null,
                    'coordinator_session_id' => $review->id, 'created_at' => $now, 'updated_at' => $now,
                ];

                return;
            }

            $club = $clubs->get($teacherId.'|'.$slotId) ?? ($coveredId !== null ? $clubs->get($coveredId.'|'.$slotId) : null);

            if ($club !== null) {
                $rows[$teacherId.'|'.$slotId] = [
                    'school_id' => $school->id, 'teacher_id' => $teacherId, 'date' => $day, 'schedule_slot_id' => $slotId,
                    'classroom_id' => $club->classroom_id, 'is_absent' => false, 'held_classroom_id' => null,
                    'covered_teacher_id' => $coveredId, 'teacher_incident_id' => $incidentId, 'club_session_id' => $club->id, 'coordinator_session_id' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ];

                return;
            }

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
                'covered_teacher_id' => $coveredId, 'teacher_incident_id' => $incidentId, 'club_session_id' => null, 'coordinator_session_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        };
        // Hora con incidencia: queda oculta y guarda su aula (la usa quien cubre; regresa si se borra la incidencia).
        $absent = function (int $teacherId, int $slotId, ?int $heldClassroomId, ?int $incidentId) use (&$rows, $school, $day, $now): void {
            $rows[$teacherId.'|'.$slotId] = [
                'school_id' => $school->id, 'teacher_id' => $teacherId, 'date' => $day, 'schedule_slot_id' => $slotId,
                'classroom_id' => null, 'is_absent' => true, 'held_classroom_id' => $heldClassroomId,
                'covered_teacher_id' => null, 'teacher_incident_id' => $incidentId, 'club_session_id' => null, 'coordinator_session_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        };

        $scheduled = [];
        foreach ($teachers as $teacher) {
            foreach ($teacher->scheduleBlocks->where('weekday', $date->dayOfWeekIso) as $block) {
                $slot = $slots->get($block->schedule_slot_id);

                if ($slot !== null && $this->slotAppliesOn($slot, $date->dayOfWeekIso)) {
                    $scheduled[$teacher->id][$slot->id] = true;

                    $absence = $absenceOf($teacher->id, $slot);
                    $isHoliday || $absence !== null
                        ? $absent($teacher->id, $slot->id, $classroomOf($teacher->id, $slot->id), $absence?->id)
                        : $add($teacher->id, $slot->id, $classroomOf($teacher->id, $slot->id));
                }
            }
        }

        // "Cubre horas": las horas (que el cubierto tenía en su horario) pasan a quien cubre, en la misma aula.
        foreach ($incidents->filter(fn (TeacherIncident $incident) => $incident->type->isCoverage()) as $incident) {
            foreach ($slots as $slot) {
                if ($isHoliday || ! $incident->covers($date, $slot) || ! isset($scheduled[$incident->covered_teacher_id][$slot->id])
                    || isset($rows[$incident->teacher_id.'|'.$slot->id])) {
                    continue;
                }

                $add($incident->teacher_id, $slot->id, $classroomOf($incident->covered_teacher_id, $slot->id), $incident->covered_teacher_id, $incident->id);
            }
        }

        return $rows;
    }

    /**
     * Guarda el día actualizando las horas que ya existían (conservan su id, al
     * que se ligarán las reservaciones de la agenda), creando las nuevas y
     * quitando las que ya no aplican.
     *
     * @param  array<string, array<string, mixed>>  $rows  "teacher|bloque" => valores
     */
    private function syncDay(School $school, string $day, array $rows): void
    {
        $query = fn () => TeacherAvailability::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $day);
        $current = $query()->get()->keyBy(fn (TeacherAvailability $cell) => $cell->teacher_id.'|'.$cell->schedule_slot_id);

        // Primero se liberan las aulas del día para que el intercambio de aulas no choque con la regla "un aula, un teacher".
        $query()->whereNotNull('classroom_id')->update(['classroom_id' => null]);

        $query()->whereKey($current->keys()->diff(array_keys($rows))->map(fn (string $key) => $current[$key]->id)->values()->all())->delete();

        $new = [];
        foreach ($rows as $key => $values) {
            if (isset($current[$key])) {
                TeacherAvailability::withoutGlobalScope('school')->whereKey($current[$key]->id)
                    ->update(collect($values)->except(['created_at'])->all());
            } else {
                $new[] = $values;
            }
        }

        if ($new !== []) {
            TeacherAvailability::withoutGlobalScope('school')->insert($new);
        }
    }

    /** Asigna (o quita) el aula de una celda del panel. */
    public function setClassroom(TeacherAvailability $cell, ?int $classroomId): void
    {
        // La hora de un club o de una revisión del coordinador se cambia desde su programación.
        if ($cell->club_session_id !== null || $cell->coordinator_session_id !== null) {
            throw ValidationException::withMessages(['classroom' => __('availability.validation.club_hour')]);
        }

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
        $monday = self::day($monday)->startOfWeek(CarbonInterface::MONDAY);

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
                    && ! $incidents->contains(fn (TeacherIncident $other) => $other->teacher_id === $teacher->id && $other->type->isAbsence() && $other->covers($day, $slot));

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
                    && ! $incidents->contains(fn (TeacherIncident $other) => $other->teacher_id === $teacher->id && $other->type->isAbsence() && $other->covers($day, $slot));
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
