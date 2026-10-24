<?php

namespace App\Actions\Coordination;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Models\CoordinatorActivity;
use App\Models\CoordinatorSession;
use App\Models\Holiday;
use App\Models\ScheduleSlot;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Programa (o cambia) una hora del coordinador:
 * - una actividad por hora (el coordinador hace una cosa a la vez); no en festivos;
 * - si la actividad ocupa la hora del teacher (Revisión de Planeaciones) se elige un teacher
 *   que tenga esa hora libre; esa hora deja de servir para clases y clubs y se marca en el panel;
 *   las demás actividades son "Actividad Coordinación" (sin teacher);
 * - las horas del mes de la actividad no pueden pasar de sus horas autorizadas (HA).
 */
final class ScheduleCoordinatorHour
{
    public function __construct(private AvailabilityPlanner $planner) {}

    /** Horas de un bloque de horario. */
    public static function slotHours(ScheduleSlot $slot): float
    {
        return max(0.0, (strtotime($slot->ends_at) - strtotime($slot->starts_at)) / 3600);
    }

    /** Horas programadas de una actividad en el mes de esa fecha (sin contar la que se edita). */
    public function monthHours(CoordinatorActivity $activity, CarbonImmutable $date, ?int $exceptSessionId = null): float
    {
        return (float) CoordinatorSession::withoutGlobalScope('school')->where('coordinator_activity_id', $activity->id)
            ->whereBetween('date', [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()])
            ->when($exceptSessionId, fn ($query, $id) => $query->whereKeyNot($id))
            ->with('slot')->get()->sum(fn (CoordinatorSession $session) => $session->hours());
    }

    /**
     * @param  array{coordinator_activity_id: int, teacher_id: ?int, date: string, schedule_slot_id: int, repeats?: bool}  $data
     */
    public function save(School $school, array $data, ?CoordinatorSession $session = null, ?int $userId = null): CoordinatorSession
    {
        $date = CarbonImmutable::parse($data['date']);
        $slot = $this->planner->slots($school)->firstWhere('id', $data['schedule_slot_id']);
        $activity = CoordinatorActivity::withoutGlobalScope('school')->where('school_id', $school->id)->where('is_active', true)
            ->find($data['coordinator_activity_id']);

        if (($problem = $this->problem($school, $data, $date, $slot, $activity, $session)) !== null) {
            throw ValidationException::withMessages([$problem[0] => $problem[1]]);
        }

        $previousDate = $session?->date?->toDateString();

        return DB::transaction(function () use ($school, $data, $session, $userId, $activity, $date, $previousDate): CoordinatorSession {
            $session ??= new CoordinatorSession(['school_id' => $school->id, 'user_id' => $userId]);
            $session->fill([
                'coordinator_activity_id' => $activity->id,
                'teacher_id' => $activity->uses_teacher_hour ? $data['teacher_id'] : null,
                'date' => $date->toDateString(),
                'schedule_slot_id' => $data['schedule_slot_id'],
                'repeats' => (bool) ($data['repeats'] ?? false),
            ])->save();

            $this->refresh($school, $date->toDateString());
            if ($previousDate !== null && $previousDate !== $date->toDateString()) {
                $this->refresh($school, $previousDate);
            }

            return $session;
        });
    }

    public function delete(CoordinatorSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $date = $session->date->toDateString();
            $session->delete();
            $this->refresh(School::findOrFail($session->school_id), $date);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}|null campo => motivo, o null si se puede
     */
    public function problem(School $school, array $data, CarbonImmutable $date, ?ScheduleSlot $slot, ?CoordinatorActivity $activity, ?CoordinatorSession $session = null): ?array
    {
        if ($activity === null) {
            return ['activityId', __('coordination.validation.activity')];
        }

        if ($slot === null || ! $this->planner->slotAppliesOn($slot, $date->dayOfWeekIso)) {
            return ['slot', __('coordination.validation.slot')];
        }

        if (Holiday::withoutGlobalScope('school')->where('school_id', $school->id)->covering($date->toDateString())->exists()) {
            return ['slot', __('coordination.validation.holiday')];
        }

        $busy = CoordinatorSession::withoutGlobalScope('school')->where('school_id', $school->id)->whereDate('date', $date->toDateString())
            ->where('schedule_slot_id', $slot->id)->when($session, fn ($query) => $query->whereKeyNot($session->id))->exists();
        if ($busy) {
            return ['slot', __('coordination.validation.busy')];
        }

        if ($activity->uses_teacher_hour
            && ! in_array((int) ($data['teacher_id'] ?? 0), $this->planner->availableTeacherIds($school, $date, $slot, null, $session?->id), true)) {
            return ['teacherId', __('coordination.validation.teacher')];
        }

        $hours = $this->monthHours($activity, $date, $session?->id) + self::slotHours($slot);
        if ($hours > $activity->authorized_hours + 0.001) {
            return ['activityId', __('coordination.validation.over', ['activity' => $activity->name, 'hours' => (float) $activity->authorized_hours])];
        }

        return null;
    }

    /** Si la semana está armada en el panel, se recalcula ese día. */
    private function refresh(School $school, string $day): void
    {
        $this->planner->rebuildRange($school, CarbonImmutable::parse($day), CarbonImmutable::parse($day));
    }
}
