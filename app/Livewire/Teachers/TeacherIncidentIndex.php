<?php

namespace App\Livewire\Teachers;

use App\Actions\Teachers\AvailabilityPlanner;
use App\Actions\Teachers\SyncTeacherStatus;
use App\Enums\TeacherIncidentType;
use App\Enums\TeacherStatus;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherIncident;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Teachers → Incidencias: permisos, vacaciones, incapacidad, faltas, eventos
 * imprevistos, "Cubre horas", Baja y Reingreso. Al guardar o borrar, el panel de
 * disponibilidad de esas fechas se actualiza solo. Baja y Reingreso cambian el
 * estatus del teacher; no se editan: se borra la última y se registra de nuevo.
 */
#[Layout('layouts.app')]
class TeacherIncidentIndex extends Component
{
    use WithPagination;

    #[Url(as: 'teacher', except: '')]
    public string $teacher = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'mes', except: '')]
    public string $month = '';

    public ?bool $editing = null;

    public ?int $incidentId = null;

    public ?int $teacherId = null;

    public string $incidentType = 'vacation';

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $allDay = true;

    public ?int $fromSlotId = null;

    public ?int $toSlotId = null;

    public ?int $coveredTeacherId = null;

    public string $reason = '';

    public ?int $confirmingDeletionId = null;

    public function mount(): void
    {
        if (auth()->user()->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('availability.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $this->authorize('viewAny', TeacherIncident::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['teacher', 'type', 'month'], true)) {
            $this->resetPage();
        }
    }

    public function edit(?int $incidentId = null): void
    {
        $incident = $incidentId ? $this->find($incidentId) : null;
        $incident ? $this->authorize('update', $incident) : $this->authorize('create', TeacherIncident::class);
        $this->resetValidation();

        $today = now(config('appingles.timezone'))->toDateString();
        $this->incidentId = $incident?->id;
        $this->teacherId = $incident?->teacher_id;
        $this->incidentType = $incident?->type->value ?? TeacherIncidentType::Vacation->value;
        $this->startsOn = $incident?->starts_on->toDateString() ?? $today;
        $this->endsOn = $incident?->ends_on->toDateString() ?? $today;
        $this->allDay = $incident === null || $incident->from_slot_id === null;
        $this->fromSlotId = $incident?->from_slot_id;
        $this->toSlotId = $incident?->to_slot_id;
        $this->coveredTeacherId = $incident?->covered_teacher_id;
        $this->reason = (string) $incident?->reason;
        $this->editing = true;
    }

    public function save(AvailabilityPlanner $planner, SyncTeacherStatus $syncStatus): void
    {
        $incident = $this->incidentId ? $this->find($this->incidentId) : null;
        $incident ? $this->authorize('update', $incident) : $this->authorize('create', TeacherIncident::class);

        $schoolId = auth()->user()->school_id;
        $isCoverage = $this->incidentType === TeacherIncidentType::Covers->value;
        $statusType = TeacherIncidentType::tryFrom($this->incidentType);
        $isStatus = $statusType?->isStatusChange() ?? false;

        // Baja y reingreso son de un solo día y sin horas.
        if ($isStatus) {
            $this->endsOn = $this->startsOn;
            $this->allDay = true;
        }

        if ($incident !== null && ($isStatus || $incident->type->isStatusChange())) {
            throw ValidationException::withMessages(['incidentType' => __('availability.validation.status_not_editable')]);
        }
        $inSchool = fn (string $table) => Rule::exists($table, 'id')->where('school_id', $schoolId);

        $this->validate([
            'teacherId' => ['required', 'integer', $inSchool('teachers')],
            'incidentType' => ['required', Rule::enum(TeacherIncidentType::class)],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn', 'before_or_equal:'.Carbon::parse($this->startsOn ?: 'today')->addYear()->toDateString()],
            'fromSlotId' => [Rule::requiredIf(! $this->allDay), 'nullable', 'integer', $inSchool('schedule_slots')],
            'toSlotId' => [Rule::requiredIf(! $this->allDay), 'nullable', 'integer', $inSchool('schedule_slots')],
            'coveredTeacherId' => [Rule::requiredIf($isCoverage), 'nullable', 'integer', 'different:teacherId', $inSchool('teachers')],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'coveredTeacherId.different' => __('availability.validation.cover_self'),
        ], $this->attributeNames());

        $data = [
            'school_id' => $schoolId,
            'teacher_id' => $this->teacherId,
            'type' => $this->incidentType,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'from_slot_id' => $this->allDay ? null : $this->fromSlotId,
            'to_slot_id' => $this->allDay ? null : $this->toSlotId,
            'covered_teacher_id' => $isCoverage ? $this->coveredTeacherId : null,
            'reason' => trim($this->reason),
            'user_id' => $incident?->user_id ?? auth()->id(),
        ];

        if ($isStatus) {
            $this->checkStatusChange(Teacher::query()->where('school_id', $schoolId)->findOrFail($this->teacherId), $statusType, $syncStatus);

            DB::transaction(function () use ($data, $syncStatus): void {
                $saved = TeacherIncident::create($data);
                $syncStatus->handle($saved->teacher, $saved->starts_on);
            });

            $this->editing = null;
            $this->dispatch('toast', type: 'success', message: __('availability.messages.incident_saved'));

            return;
        }

        $candidate = ($incident ?? new TeacherIncident)->fill($data);
        $candidate->unsetRelation('fromSlot')->unsetRelation('toSlot')->load(['fromSlot', 'toSlot']);

        if (! $this->allDay && $candidate->fromSlot->starts_at > $candidate->toSlot->starts_at) {
            throw ValidationException::withMessages(['toSlotId' => __('availability.validation.slot_order')]);
        }

        if ($isCoverage && ($problems = $planner->coverageProblems(School::findOrFail($schoolId), $candidate)) !== []) {
            throw ValidationException::withMessages(['coveredTeacherId' => $problems]);
        }

        $previous = $incident ? [$incident->getOriginal('starts_on'), $incident->getOriginal('ends_on')] : null;

        DB::transaction(function () use ($candidate, $planner, $schoolId, $previous): void {
            $candidate->save();
            $school = School::findOrFail($schoolId);
            $planner->rebuildRange($school, $candidate->starts_on, $candidate->ends_on);

            if ($previous !== null) {
                $planner->rebuildRange($school, $previous[0], $previous[1]);
            }
        });

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('availability.messages.incident_saved'));
    }

    /** Baja solo de un teacher que no está de baja; reingreso solo de uno de baja; siempre después del último cambio. */
    private function checkStatusChange(Teacher $teacher, TeacherIncidentType $type, SyncTeacherStatus $syncStatus): void
    {
        $latest = $syncStatus->latest($teacher);
        $isOut = $latest !== null ? $latest->type === TeacherIncidentType::Termination : $teacher->status === TeacherStatus::Terminated;

        if ($type === TeacherIncidentType::Termination && $isOut) {
            throw ValidationException::withMessages(['incidentType' => __('availability.validation.already_terminated')]);
        }

        if ($type === TeacherIncidentType::Rehire && ! $isOut) {
            throw ValidationException::withMessages(['incidentType' => __('availability.validation.not_terminated')]);
        }

        if ($latest !== null && Carbon::parse($this->startsOn)->lte($latest->starts_on)) {
            throw ValidationException::withMessages(['startsOn' => __('availability.validation.status_after', ['date' => $latest->starts_on->translatedFormat('j M Y')])]);
        }
    }

    public function confirmDeletion(int $incidentId): void
    {
        $incident = $this->find($incidentId);
        $this->authorize('update', $incident);

        if (! $this->isDeletable($incident)) {
            $this->dispatch('toast', type: 'error', message: __('availability.validation.status_delete_latest'));

            return;
        }

        $this->confirmingDeletionId = $incidentId;
    }

    /** De las bajas y reingresos solo se borra el último (el estatus regresa al anterior). */
    private function isDeletable(TeacherIncident $incident): bool
    {
        return ! $incident->type->isStatusChange() || app(SyncTeacherStatus::class)->latest($incident->teacher)?->is($incident);
    }

    public function delete(AvailabilityPlanner $planner, SyncTeacherStatus $syncStatus): void
    {
        $incident = $this->find($this->confirmingDeletionId);
        $this->authorize('update', $incident);
        abort_unless($this->isDeletable($incident), 403);

        DB::transaction(function () use ($incident, $planner, $syncStatus): void {
            $incident->delete();

            $incident->type->isStatusChange()
                ? $syncStatus->handle($incident->teacher, $incident->starts_on, $incident->type)
                : $planner->rebuildRange(School::findOrFail($incident->school_id), $incident->starts_on, $incident->ends_on);
        });

        $this->confirmingDeletionId = null;
        $this->dispatch('toast', type: 'success', message: __('availability.messages.incident_deleted'));
    }

    private function find(?int $incidentId): TeacherIncident
    {
        return TeacherIncident::query()->where('school_id', auth()->user()->school_id)->findOrFail($incidentId);
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        return collect(['teacherId' => 'teacher', 'incidentType' => 'type', 'startsOn' => 'starts_on', 'endsOn' => 'ends_on',
            'fromSlotId' => 'from', 'toSlotId' => 'to', 'coveredTeacherId' => 'covered_teacher', 'reason' => 'reason'])
            ->map(fn (string $key) => Str::lower(__("availability.incident_fields.$key")))
            ->all();
    }

    /** @return LengthAwarePaginator<int, TeacherIncident> */
    #[Computed]
    public function incidents(): LengthAwarePaginator
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? Carbon::parse($this->month.'-01') : null;

        return TeacherIncident::query()
            ->where('school_id', auth()->user()->school_id)
            ->with(['teacher:id,first_name,last_name,second_last_name,short_name', 'coveredTeacher:id,first_name,short_name', 'fromSlot', 'toSlot', 'user:id,name'])
            ->when($this->teacher !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('teacher_id', (int) $this->teacher)->orWhere('covered_teacher_id', (int) $this->teacher)))
            ->when(TeacherIncidentType::tryFrom($this->type) !== null, fn (Builder $query) => $query->where('type', $this->type))
            ->when($month !== null, fn (Builder $query) => $query
                ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
                ->whereDate('ends_on', '>=', $month->toDateString()))
            ->latest('starts_on')->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function pendingDeletion(): ?TeacherIncident
    {
        return $this->confirmingDeletionId ? TeacherIncident::query()->where('school_id', auth()->user()->school_id)->with('teacher')->find($this->confirmingDeletionId) : null;
    }

    public function render(): View
    {
        $schoolId = auth()->user()->school_id;

        return view('livewire.teachers.teacher-incident-index', [
            'teacherOptions' => Teacher::query()->where('school_id', $schoolId)->orderByRaw('panel_number is null')->orderBy('panel_number')->orderBy('first_name')->get()
                ->mapWithKeys(fn (Teacher $teacher) => [$teacher->id => $teacher->fullName()])->all(),
            'typeOptions' => collect(TeacherIncidentType::cases())->mapWithKeys(fn (TeacherIncidentType $type) => [$type->value => $type->label()])->all(),
            'slotOptions' => app(AvailabilityPlanner::class)->slots(School::findOrFail($schoolId))
                ->mapWithKeys(fn ($slot) => [$slot->id => $slot->range()])->all(),
            'canManage' => auth()->user()->can('create', TeacherIncident::class),
        ])->title(__('availability.incidents_title'));
    }
}
