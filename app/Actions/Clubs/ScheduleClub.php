<?php

namespace App\Actions\Clubs;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Enums\ClubModality;
use App\Models\Classroom;
use App\Models\ClassroomClosure;
use App\Models\Club;
use App\Models\ClubSession;
use App\Models\Holiday;
use App\Models\ScheduleSlot;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Programa (o cambia) un club en un día y hora:
 * - el teacher debe tener esa hora (horario base o "Cubre horas"), sin ausencia ni otro club;
 * - ocupa un aula "para clubs" activa, libre y sin reserva para otra actividad: el presencial,
 *   un aula audiovisual física; el en línea, un aula virtual. A la misma hora caben tantos clubs
 *   presenciales como aulas físicas y tantos en línea como aulas virtuales (cada uno con su teacher);
 * - no hay clubs en días festivos.
 * Después se recalcula el día en el panel: esa hora del teacher deja de servir para clases normales.
 */
final class ScheduleClub
{
    public function __construct(private AvailabilityPlanner $planner) {}

    /**
     * Aulas para clubs activas de la escuela: físicas (presencial), virtuales (en línea) o todas.
     *
     * @return Collection<int, Classroom>
     */
    public function clubRooms(School $school, ?ClubModality $modality = null): Collection
    {
        return Classroom::withoutGlobalScope('school')->where('school_id', $school->id)
            ->where('is_club_room', true)->where('is_active', true)
            ->when($modality !== null, fn ($query) => $query->where('is_virtual', $modality === ClubModality::Online))
            ->orderByRaw('CAST(name AS UNSIGNED)')->orderBy('name')->get();
    }

    /** Aulas para clubs de esa modalidad libres ese día a esa hora (sin reserva y sin otro club). @return Collection<int, Classroom> */
    public function freeRooms(School $school, CarbonImmutable $date, ScheduleSlot $slot, ClubModality $modality, ?int $exceptSessionId = null): Collection
    {
        $rooms = $this->clubRooms($school, $modality);
        $closures = ClassroomClosure::withoutGlobalScope('school')->whereIn('classroom_id', $rooms->modelKeys())
            ->whereDate('starts_on', '<=', $date->toDateString())->whereDate('ends_on', '>=', $date->toDateString())
            ->with(['fromSlot', 'toSlot'])->get();
        $taken = ClubSession::withoutGlobalScope('school')->where('school_id', $school->id)
            ->whereDate('date', $date->toDateString())->where('schedule_slot_id', $slot->id)
            ->when($exceptSessionId, fn ($query, $id) => $query->whereKeyNot($id))
            ->pluck('classroom_id');

        return $rooms->reject(fn (Classroom $room) => $taken->contains($room->id)
            || $closures->contains(fn (ClassroomClosure $closure) => $closure->classroom_id === $room->id && $closure->covers($date, $slot)))->values();
    }

    /**
     * @param  array{club_id: int, code: string, teacher_id: int, date: string, schedule_slot_id: int, modality: string, classroom_id?: ?int}  $data
     */
    public function save(School $school, array $data, ?ClubSession $session = null, ?int $userId = null): ClubSession
    {
        $date = CarbonImmutable::parse($data['date']);
        $slot = $this->planner->slots($school)->firstWhere('id', $data['schedule_slot_id']);
        $problem = $this->problem($school, $data, $date, $slot, $session);

        if ($problem !== null) {
            throw ValidationException::withMessages([$problem[0] => $problem[1]]);
        }

        $room = $this->room($school, $data, $date, $slot, $session);
        $previousDate = $session?->date?->toDateString();

        return DB::transaction(function () use ($school, $data, $session, $userId, $room, $date, $previousDate): ClubSession {
            $session ??= new ClubSession(['school_id' => $school->id, 'user_id' => $userId]);
            $session->fill([
                'club_id' => $data['club_id'],
                'code' => $data['code'],
                'teacher_id' => $data['teacher_id'],
                'classroom_id' => $room->id,
                'date' => $date->toDateString(),
                'schedule_slot_id' => $data['schedule_slot_id'],
                'modality' => ClubModality::from($data['modality']),
            ])->save();

            $this->refresh($school, $date->toDateString());
            if ($previousDate !== null && $previousDate !== $date->toDateString()) {
                $this->refresh($school, $previousDate);
            }

            return $session;
        });
    }

    public function delete(ClubSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $date = $session->date->toDateString();
            $session->delete();
            $this->refresh(School::findOrFail($session->school_id), $date);
        });
    }

    /**
     * Motivo por el que no se puede programar (campo => mensaje), o null si se puede.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}|null
     */
    public function problem(School $school, array $data, CarbonImmutable $date, ?ScheduleSlot $slot, ?ClubSession $session = null): ?array
    {
        $club = Club::withoutGlobalScope('school')->where('school_id', $school->id)->where('is_active', true)->find($data['club_id'] ?? null);
        $code = $club?->codeInfo((string) ($data['code'] ?? ''));
        $modality = ClubModality::tryFrom((string) ($data['modality'] ?? '')) ?? ClubModality::InPerson;

        return match (true) {
            $code === null || ! ($code['is_active'] ?? true) => ['code', __('clubs.validation.code')],
            $slot === null || ! $this->planner->slotAppliesOn($slot, $date->dayOfWeekIso) => ['slot', __('clubs.validation.slot')],
            Holiday::withoutGlobalScope('school')->where('school_id', $school->id)->covering($date->toDateString())->exists() => ['slot', __('clubs.validation.holiday')],
            ! in_array((int) ($data['teacher_id'] ?? 0), $this->planner->availableTeacherIds($school, $date, $slot, $session?->id), true) => ['teacherId', __('clubs.validation.teacher')],
            $this->freeRooms($school, $date, $slot, $modality, $session?->id)->isEmpty() => ['slot', __('clubs.validation.no_room_'.$modality->value)],
            ! empty($data['classroom_id']) && ! $this->freeRooms($school, $date, $slot, $modality, $session?->id)->contains('id', (int) $data['classroom_id']) => ['classroomId', __('clubs.validation.room_taken')],
            default => null,
        };
    }

    /** Aula elegida o la primera audiovisual libre. @param array<string, mixed> $data */
    private function room(School $school, array $data, CarbonImmutable $date, ScheduleSlot $slot, ?ClubSession $session): Classroom
    {
        $free = $this->freeRooms($school, $date, $slot, ClubModality::from($data['modality']), $session?->id);

        return (! empty($data['classroom_id']) ? $free->firstWhere('id', (int) $data['classroom_id']) : null)
            ?? ($session !== null ? $free->firstWhere('id', $session->classroom_id) : null)
            ?? $free->first();
    }

    /** Si la semana está armada en el panel, se recalcula ese día. */
    private function refresh(School $school, string $day): void
    {
        $this->planner->rebuildRange($school, CarbonImmutable::parse($day), CarbonImmutable::parse($day));
    }
}
