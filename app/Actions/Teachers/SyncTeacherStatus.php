<?php

namespace App\Actions\Teachers;

use App\Enums\TeacherIncidentType;
use App\Enums\TeacherStatus;
use App\Enums\UserStatus;
use App\Models\AvailabilityWeek;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherIncident;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * El estatus del teacher sigue a su última incidencia de Baja o Reingreso:
 * Baja → estatus Baja con su fecha (sin acceso); Reingreso → Activo. Después
 * se recalculan las semanas armadas desde esa fecha (el teacher sale o vuelve
 * al panel desde ese día; antes conserva sus horas).
 */
final class SyncTeacherStatus
{
    public function __construct(private AvailabilityPlanner $planner) {}

    /** Última baja o reingreso del teacher (por fecha). */
    public function latest(Teacher $teacher, ?int $exceptIncidentId = null): ?TeacherIncident
    {
        return TeacherIncident::withoutGlobalScope('school')->where('teacher_id', $teacher->id)
            ->whereIn('type', [TeacherIncidentType::Termination, TeacherIncidentType::Rehire])
            ->when($exceptIncidentId, fn ($query, $id) => $query->whereKeyNot($id))
            ->latest('starts_on')->latest('id')->first();
    }

    /**
     * @param  TeacherIncidentType|null  $removed  tipo de la baja o reingreso que se acaba de borrar (si no queda otra)
     */
    public function handle(Teacher $teacher, CarbonInterface $from, ?TeacherIncidentType $removed = null): void
    {
        $latest = $this->latest($teacher);

        [$status, $terminatedOn] = match (true) {
            $latest?->type === TeacherIncidentType::Termination => [TeacherStatus::Terminated, $latest->starts_on],
            $latest?->type === TeacherIncidentType::Rehire => [TeacherStatus::Active, null],
            $removed === TeacherIncidentType::Termination => [TeacherStatus::Active, null],
            default => [$teacher->status, $teacher->terminated_on],
        };

        $teacher->update(['status' => $status, 'terminated_on' => $terminatedOn]);
        $teacher->user?->update(['status' => $status->canWork() ? UserStatus::Active : UserStatus::Inactive]);

        $school = School::findOrFail($teacher->school_id);
        $lastWeek = AvailabilityWeek::withoutGlobalScope('school')->where('school_id', $school->id)->max('starts_on');

        if ($lastWeek !== null) {
            $this->planner->rebuildRange($school, $from, CarbonImmutable::parse($lastWeek)->addDays(6));
        }
    }
}
