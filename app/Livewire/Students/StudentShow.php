<?php

namespace App\Livewire\Students;

use App\Actions\Students\ChangeStudentStatus;
use App\Actions\Users\SendInvitation;
use App\Enums\StudentStatus;
use App\Enums\StudentTermMovementType;
use App\Livewire\Forms\EnrollmentForm;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentAbsence;
use App\Models\StudentTermMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Expediente del alumno: datos, inscripciones, histórico de estatus (con
 * motivo), movimientos del plazo, ausencias, tutores y encuesta. Consultar requiere "Expedientes";
 * modificar, ser administrador de la escuela o Servicios escolares.
 */
#[Layout('layouts.app')]
class StudentShow extends Component
{
    use Concerns\EnrollmentOptions;

    #[Locked]
    public int $studentId;

    public EnrollmentForm $enrollment;

    public ?bool $editingEnrollment = null;

    public ?bool $changingStatus = null;

    public string $newStatus = '';

    public string $statusDate = '';

    public string $statusReason = '';

    public ?bool $addingAbsence = null;

    public ?int $absenceId = null;

    public string $absenceFrom = '';

    public string $absenceTo = '';

    public string $absenceReason = '';

    public ?bool $addingMovement = null;

    public string $movementType = '';

    public string $movementDate = '';

    public ?int $movementDays = null;

    public string $movementReason = '';

    /** Mensualidad abierta que se está cerrando. */
    public ?int $closingMovementId = null;

    public string $closeDate = '';

    public ?bool $linkingGuardian = null;

    /** '' = tutor nuevo; un ID = tutor ya registrado en la escuela. */
    public string $guardianId = '';

    public string $guardianName = '';

    public string $guardianPhone = '';

    public string $guardianEmail = '';

    public string $guardianRelationship = '';

    /** @var list<string> */
    public array $guardianReceives = [];

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->studentId = $student->id;
    }

    #[Computed]
    public function student(): Student
    {
        return Student::query()->where('school_id', auth()->user()->school_id)
            ->with([
                'user', 'type', 'informationSource', 'state', 'municipality',
                'enrollments' => fn ($query) => $query->latest('registered_on')->latest('id'),
                'enrollments.salesAdvisor', 'enrollments.plan', 'enrollments.startBook', 'enrollments.startLesson', 'enrollments.startActivity',
                'statusChanges' => fn ($query) => $query->latest('changed_on')->latest('id'),
                'statusChanges.user:id,name',
                'absences' => fn ($query) => $query->latest('starts_on'),
                'termMovements.user:id,name',
                'guardians.user:id,email,password',
                'surveyAnswers.question',
            ])
            ->findOrFail($this->studentId);
    }

    private function authorizeUpdate(): Student
    {
        $student = $this->student;
        $this->authorize('update', $student);

        return $student;
    }

    // ── Inscripciones ──────────────────────────────────────────────

    public function editEnrollment(?int $enrollmentId = null): void
    {
        $student = $this->authorizeUpdate();
        $this->resetValidation();

        $this->enrollment->setEnrollment($enrollmentId ? $student->enrollments->firstWhere('id', $enrollmentId) ?? abort(404) : null);
        $this->editingEnrollment = true;
    }

    public function updatedEnrollmentStudentPlanId(): void
    {
        $this->enrollment->applyPlanInstallments();
    }

    public function updatedEnrollmentStartBookId(): void
    {
        $this->enrollment->start_lesson_id = null;
    }

    public function saveEnrollment(): void
    {
        $student = $this->authorizeUpdate();

        // La inscripción que se edita debe ser de este alumno, aunque alguien la cambie en el navegador.
        abort_if($this->enrollment->enrollment !== null && $this->enrollment->enrollment->student_id !== $student->id, 404);

        $this->enrollment->save($student);
        $this->editingEnrollment = null;
        unset($this->student);

        $this->dispatch('toast', type: 'success', message: __('students.messages.enrollment_saved'));
    }

    // ── Estatus ────────────────────────────────────────────────────

    public function askStatusChange(): void
    {
        $student = $this->authorizeUpdate();
        $this->resetValidation();

        $this->newStatus = $student->status->value;
        $this->statusDate = now(config('appingles.timezone'))->toDateString();
        $this->statusReason = '';
        $this->changingStatus = true;
    }

    public function changeStatus(ChangeStudentStatus $changeStatus): void
    {
        $student = $this->authorizeUpdate();
        $status = StudentStatus::tryFrom($this->newStatus);

        $this->validate([
            'newStatus' => ['required', Rule::enum(StudentStatus::class)],
            'statusDate' => ['required', 'date', 'before_or_equal:today'],
            'statusReason' => [Rule::requiredIf($status?->requiresReason() ?? false), 'nullable', 'string', 'max:1000'],
        ], attributes: [
            'newStatus' => Str::lower(__('students.fields.status')),
            'statusDate' => Str::lower(__('students.status_change.date')),
            'statusReason' => Str::lower(__('students.status_change.reason')),
        ]);

        $changeStatus->handle($student, $status, $this->statusReason, Carbon::parse($this->statusDate));

        $this->changingStatus = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.status_changed', ['status' => $status->label()]));
    }

    // ── Ausencias (pausas) ─────────────────────────────────────────

    public function editAbsence(?int $absenceId = null): void
    {
        $student = $this->authorizeUpdate();
        $this->resetValidation();

        $absence = $absenceId ? $student->absences->firstWhere('id', $absenceId) ?? abort(404) : null;
        $this->absenceId = $absence?->id;
        $this->absenceFrom = (string) ($absence?->starts_on?->toDateString() ?? now(config('appingles.timezone'))->toDateString());
        $this->absenceTo = (string) $absence?->ends_on?->toDateString();
        $this->absenceReason = (string) $absence?->reason;
        $this->addingAbsence = true;
    }

    public function saveAbsence(): void
    {
        $student = $this->authorizeUpdate();

        $data = $this->validate([
            'absenceFrom' => ['required', 'date'],
            'absenceTo' => ['required', 'date', 'after_or_equal:absenceFrom'],
            'absenceReason' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'absenceFrom' => Str::lower(__('students.absences.from')),
            'absenceTo' => Str::lower(__('students.absences.to')),
            'absenceReason' => Str::lower(__('students.absences.reason')),
        ]);

        // Hasta 60 días naturales por ciclo del programa (se reinician al reinscribirse).
        $requested = (int) Carbon::parse($data['absenceFrom'])->diffInDays(Carbon::parse($data['absenceTo'])) + 1;
        $used = $student->absenceDaysInCycle($this->absenceId);

        if ($used + $requested > Student::MAX_ABSENCE_DAYS) {
            throw ValidationException::withMessages(['absenceTo' => __('students.absences.limit', [
                'requested' => $requested, 'used' => $used, 'max' => Student::MAX_ABSENCE_DAYS, 'left' => max(0, Student::MAX_ABSENCE_DAYS - $used),
            ])]);
        }

        $absence = $this->absenceId
            ? $student->absences->firstWhere('id', $this->absenceId) ?? abort(404)
            : new StudentAbsence(['school_id' => $student->school_id, 'student_id' => $student->id, 'user_id' => auth()->id()]);

        $absence->fill([
            'starts_on' => $data['absenceFrom'],
            'ends_on' => $data['absenceTo'] ?: null,
            'reason' => trim($data['absenceReason']) ?: null,
        ])->save();

        $this->addingAbsence = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.absence_saved'));
    }

    // ── Movimientos del plazo ──────────────────────────────────────

    public function addMovement(): void
    {
        $this->authorizeUpdate();
        $this->resetValidation();

        $this->movementType = StudentTermMovementType::Extension->value;
        $this->movementDate = now(config('appingles.timezone'))->toDateString();
        $this->movementDays = null;
        $this->movementReason = '';
        $this->addingMovement = true;
    }

    public function saveMovement(): void
    {
        $student = $this->authorizeUpdate();
        $type = StudentTermMovementType::tryFrom($this->movementType);

        $data = $this->validate([
            'movementType' => ['required', Rule::in(array_keys(StudentTermMovementType::manualOptions()))],
            'movementDate' => ['required', 'date'],
            'movementDays' => ['required', 'integer', 'min:1', 'max:'.($type?->maxDays() ?? 1)],
            'movementReason' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'movementType' => Str::lower(__('students.term.type')),
            'movementDate' => Str::lower(__('students.term.date')),
            'movementDays' => Str::lower(__('students.term.days')),
            'movementReason' => Str::lower(__('students.term.reason')),
        ]);

        if ($type === StudentTermMovementType::OpenMonthly && $student->openMonthly() !== null) {
            throw ValidationException::withMessages(['movementType' => __('students.term.already_open')]);
        }

        StudentTermMovement::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'type' => $type,
            'occurred_on' => $data['movementDate'],
            'days' => $data['movementDays'],
            'reason' => trim($data['movementReason']) ?: null,
            'user_id' => auth()->id(),
        ]);

        $this->addingMovement = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.movement_saved'));
    }

    /** La reinscripción se quita desde su ficha (borrando la fecha de reinscripción). */
    public function deleteMovement(int $movementId): void
    {
        $student = $this->authorizeUpdate();
        $movement = $student->termMovements->firstWhere('id', $movementId) ?? abort(404);
        abort_unless($movement->type->isManual(), 403);

        $movement->delete();
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.movement_deleted'));
    }

    public function askCloseMovement(int $movementId): void
    {
        $student = $this->authorizeUpdate();
        $movement = $student->termMovements->firstWhere('id', $movementId) ?? abort(404);
        abort_unless($movement->isOpen(), 404);
        $this->resetValidation();

        $this->closingMovementId = $movement->id;
        $this->closeDate = now(config('appingles.timezone'))->toDateString();
    }

    /** Cierra la mensualidad abierta: sus días pasan de estimados a reales (hasta la fecha de cierre). */
    public function closeMovement(): void
    {
        $student = $this->authorizeUpdate();
        $movement = $student->termMovements->firstWhere('id', $this->closingMovementId) ?? abort(404);
        abort_unless($movement->isOpen(), 404);
        $startsOn = $student->courseEndsBefore($movement);

        $this->validate([
            'closeDate' => array_filter(['required', 'date', $startsOn ? 'after:'.$startsOn->toDateString() : null]),
        ], attributes: ['closeDate' => Str::lower(__('students.term.close_date'))]);

        $closedOn = Carbon::parse($this->closeDate);
        $movement->update([
            'closed_on' => $closedOn,
            'days' => $startsOn ? min(StudentTermMovementType::OpenMonthly->maxDays(), (int) $startsOn->diffInDays($closedOn)) : $movement->days,
        ]);

        $this->closingMovementId = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.movement_closed'));
    }

    // ── Tutores ────────────────────────────────────────────────────

    public function linkGuardian(): void
    {
        $this->authorizeUpdate();
        $this->resetValidation();
        $this->reset('guardianId', 'guardianName', 'guardianPhone', 'guardianEmail', 'guardianRelationship', 'guardianReceives');
        $this->linkingGuardian = true;
    }

    public function saveGuardian(): void
    {
        $student = $this->authorizeUpdate();
        $schoolId = $student->school_id;
        $existing = $this->guardianId !== '';

        $this->validate([
            'guardianId' => ['nullable', Rule::exists('guardians', 'id')->where('school_id', $schoolId)],
            'guardianName' => [Rule::requiredIf(! $existing), 'nullable', 'string', 'max:160'],
            'guardianPhone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'guardianEmail' => ['nullable', 'email:rfc', 'max:255'],
            'guardianRelationship' => ['nullable', 'string', 'max:40'],
            'guardianReceives' => ['array'],
            'guardianReceives.*' => [Rule::in(Guardian::REPORTS)],
        ], attributes: [
            'guardianName' => Str::lower(__('students.guardians.name')),
            'guardianPhone' => Str::lower(__('students.guardians.phone')),
            'guardianEmail' => Str::lower(__('students.guardians.email')),
        ]);

        $guardian = $existing
            ? Guardian::where('school_id', $schoolId)->findOrFail((int) $this->guardianId)
            : Guardian::create([
                'school_id' => $schoolId,
                'name' => Str::upper(trim($this->guardianName)),
                'mobile_phone' => trim($this->guardianPhone) ?: null,
                'email' => $this->guardianEmail,
                'receives' => array_values($this->guardianReceives),
            ]);

        $student->guardians()->syncWithoutDetaching([$guardian->id => ['relationship' => trim($this->guardianRelationship) ?: null]]);

        $this->linkingGuardian = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.guardian_linked'));
    }

    public function unlinkGuardian(int $guardianId): void
    {
        $student = $this->authorizeUpdate();
        $student->guardians()->detach($guardianId);

        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.guardian_unlinked'));
    }

    /** @return array<int, string> */
    #[Computed]
    public function guardianOptions(): array
    {
        return Guardian::query()->where('school_id', auth()->user()->school_id)->orderBy('name')->get(['id', 'name', 'mobile_phone'])
            ->mapWithKeys(fn (Guardian $guardian) => [$guardian->id => trim($guardian->name.' '.($guardian->mobile_phone ? "· {$guardian->mobile_phone}" : ''))])
            ->all();
    }

    // ── Acceso ─────────────────────────────────────────────────────

    public function sendAccess(SendInvitation $sendInvitation): void
    {
        $student = $this->student;
        $this->authorize('sendAccess', $student);

        $this->dispatch('toast', ...$sendInvitation->handle($student->user)
            ? ['type' => 'success', 'message' => __('students.messages.access_sent', ['email' => $student->user->email])]
            : ['type' => 'error', 'message' => __('users.messages.mail_failed')]);
    }

    public function render(): View
    {
        return view('livewire.students.student-show', [
            'canUpdate' => auth()->user()->can('update', $this->student),
            'statuses' => collect(StudentStatus::cases())->mapWithKeys(fn (StudentStatus $s) => [$s->value => $s->label()])->all(),
            'reports' => collect(Guardian::REPORTS)->mapWithKeys(fn (string $report) => [$report => __("students.guardians.reports.$report")])->all(),
            'timezone' => config('appingles.timezone'),
            'movementTypes' => StudentTermMovementType::manualOptions(),
        ])->title($this->student->fullName());
    }
}
